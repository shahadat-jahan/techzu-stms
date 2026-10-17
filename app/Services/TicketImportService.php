<?php

namespace App\Services;

use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Events\TicketAssigned;
use App\Events\TicketCreated;
use App\Models\Category;
use App\Models\Import;
use App\Models\ImportError;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class TicketImportService
{
    private const CHUNK_SIZE = 500;

    private const COLUMNS = [
        'external_ref',
        'subject',
        'description',
        'priority',
        'category',
        'status',
        'customer_email',
        'assigned_agent_email',
        'created_at',
    ];

    /** @var array<string,array{0:string,1:string}> priority value => [due_at, sla_warning_at] */
    private array $slaCache = [];

    private ?CarbonInterface $slaStart = null;

    public function __construct(
        private readonly TicketNumberGeneratorService $numberGenerator,
        private readonly SlaService $slaService,
    ) {}

    public function process(Import $import): void
    {
        $filePath = storage_path("app/{$import->path}");

        if (! file_exists($filePath)) {
            Log::channel('imports')->error('CSV file not found', ['import_id' => $import->id, 'path' => $import->path]);
            throw new \Exception("CSV file not found: {$import->path}");
        }

        try {
            $file = fopen($filePath, 'r');

            if ($file === false) {
                throw new \Exception("Could not open CSV file: {$filePath}");
            }

            try {
                $header = fgetcsv($file);

                if ($header === false) {
                    throw new \Exception('CSV file is empty or invalid');
                }

                $columns = $this->mapHeader($header);

                $this->resetProgress($import);

                $this->slaCache = [];
                $this->slaStart = now();

                // Categories are a tiny table: load once per import, not per chunk.
                $categories = Category::all()
                    ->keyBy(fn ($category) => mb_strtolower(trim($category->name)));

                $chunk = [];
                $rowNumber = 1; // header is row 1

                while (($row = fgetcsv($file)) !== false) {
                    $rowNumber++;

                    if ($row === [null]) { // blank line
                        continue;
                    }

                    $chunk[] = [
                        'row' => $rowNumber,
                        'data' => $this->extract($row, $columns),
                    ];

                    if (count($chunk) >= self::CHUNK_SIZE) {
                        $this->processChunk($chunk, $import, $categories);
                        $chunk = [];
                    }
                }

                if ($chunk !== []) {
                    $this->processChunk($chunk, $import, $categories);
                }
            } finally {
                fclose($file);
            }

            $this->updateImportStatus($import);
        } finally {
            // The upload exists at this point, so remove it on every path
            // (open failure, bad header, crash, success) to free storage.
            File::delete($filePath);
        }
    }

    /**
     * @return array<string,int> column name => position in the CSV
     */
    private function mapHeader(array $header): array
    {
        $header = array_map(
            fn ($h) => mb_strtolower(trim((string) $h)),
            $header
        );

        // Strip UTF-8 BOM (Excel exports)
        $header[0] = ltrim($header[0], "\xEF\xBB\xBF");

        $positions = array_flip($header);
        $map = [];

        foreach (self::COLUMNS as $expected) {
            if (! isset($positions[$expected])) {
                throw new \Exception("Missing required CSV header: {$expected}");
            }

            $map[$expected] = $positions[$expected];
        }

        return $map;
    }

    private function extract(array $row, array $columns): array
    {
        $data = [];

        foreach ($columns as $name => $position) {
            $data[$name] = trim((string) ($row[$position] ?? ''));
        }

        return $data;
    }

    private function resetProgress(Import $import): void
    {
        ImportError::where('import_id', $import->id)->delete();

        $import->update([
            'processed_rows' => 0,
            'imported_rows' => 0,
            'failed_rows' => 0,
        ]);
    }

    /**
     * One chunk = one transaction:
     *   1. validate + de-duplicate every row (no writes)
     *   2. reserve all ticket numbers with a single lock
     *   3. insert all valid tickets with a single query
     *   4. store row errors with a single query
     *
     * @param  array<int,array{row:int,data:array}>  $chunk
     */
    private function processChunk(array $chunk, Import $import, Collection $categories): void
    {
        DB::transaction(function () use ($chunk, $import, $categories) {
            $rows = array_column($chunk, 'data');

            $customers = $this->usersByEmail(array_column($rows, 'customer_email'));
            $agents = $this->usersByEmail(array_column($rows, 'assigned_agent_email'), 'agent');
            $existing = $this->existingExternalRefs(array_column($rows, 'external_ref'));

            $seen = [];
            $valid = [];
            $errors = []; // [rowNumber, data, message]

            foreach ($chunk as ['row' => $rowNumber, 'data' => $data]) {
                try {
                    $parsed = $this->validateRow($data, $customers, $categories, $agents);
                    $ref = $data['external_ref'];

                    if (isset($existing[$ref])) {
                        throw new \DomainException('Duplicate external_ref in database');
                    }

                    if (isset($seen[$ref])) {
                        throw new \DomainException('Duplicate external_ref in file');
                    }

                    $seen[$ref] = true;
                    $valid[] = ['row' => $rowNumber, 'data' => $data, 'parsed' => $parsed];
                } catch (\Throwable $e) {
                    $errors[] = [$rowNumber, $data, $e->getMessage()];
                }
            }

            $insertedTicketNumbers = $this->insertTickets($valid, $customers, $categories, $agents, $errors);

            $this->recordErrors($import, $errors);

            $import->increment('processed_rows', count($chunk));
            $import->increment('imported_rows', count($insertedTicketNumbers));
            $import->increment('failed_rows', count($errors));

            // Dispatch creation/assignment events after the chunk commits so
            // notifications only fire for tickets that were actually inserted.
            if ($insertedTicketNumbers !== []) {
                DB::afterCommit(function () use ($insertedTicketNumbers): void {
                    $this->dispatchImportEvents($insertedTicketNumbers);
                });
            }
        });
    }

    /**
     * Bulk insert; if the batch is rejected by the database, retry row by row
     * (each in its own savepoint) so only the offending rows are reported.
     *
     * @param  array<int,array{row:int,data:array,parsed:array}>  $valid
     * @param  array<int,array{0:int,1:array,2:string}>  $errors  appended to
     * @return array<int,string> ticket numbers that were successfully inserted
     */
    private function insertTickets(array $valid, Collection $customers, Collection $categories, Collection $agents, array &$errors): array
    {
        if ($valid === []) {
            return [];
        }

        $now = now()->format('Y-m-d H:i:s');

        // One lock + one update for the whole chunk (kept as late as possible:
        // the sequence row stays locked until this chunk's transaction commits).
        $numbers = $this->numberGenerator->reserve(now()->year, count($valid));

        $payload = [];
        foreach ($valid as $i => $item) {
            $payload[$i] = $this->ticketRow($item['data'], $item['parsed'], $numbers[$i], $now, $customers, $categories, $agents);
        }

        try {
            // Nested transaction = savepoint, so a failure cannot abort the chunk.
            DB::transaction(fn () => Ticket::insert(array_values($payload)));

            return array_column($payload, 'ticket_number');
        } catch (\Throwable) {
            // fall through: isolate the bad row(s)
        }

        $inserted = [];

        foreach ($payload as $i => $row) {
            try {
                DB::transaction(fn () => Ticket::insert([$row]));
                $inserted[] = $row['ticket_number'];
            } catch (\Throwable $e) {
                $errors[] = [$valid[$i]['row'], $valid[$i]['data'], $e->getMessage()];
            }
        }

        return $inserted;
    }

    /**
     * Dispatch TicketCreated and TicketAssigned events for imported tickets so
     * customers and assigned agents receive notifications. Runs after the
     * chunk's transaction commits via DB::afterCommit.
     *
     * @param  array<int,string>  $ticketNumbers
     */
    private function dispatchImportEvents(array $ticketNumbers): void
    {
        $tickets = Ticket::whereIn('ticket_number', $ticketNumbers)
            ->with(['customer', 'agent'])
            ->get()
            ->keyBy('ticket_number');

        foreach ($ticketNumbers as $ticketNumber) {
            $ticket = $tickets[$ticketNumber] ?? null;
            if ($ticket === null) {
                continue;
            }

            TicketCreated::dispatch($ticket);

            if ($ticket->assigned_to !== null && $ticket->agent !== null) {
                TicketAssigned::dispatch($ticket, $ticket->agent);
            }
        }
    }

    private function usersByEmail(array $emails, ?string $role = null): Collection
    {
        $emails = array_values(array_unique(array_filter(array_map('mb_strtolower', $emails))));

        if ($emails === []) {
            return collect();
        }

        $placeholders = implode(',', array_fill(0, count($emails), '?'));

        $query = User::whereRaw("LOWER(email) IN ({$placeholders})", $emails);

        if ($role !== null) {
            $query->where('role', $role);
        }

        return $query->get()->keyBy(fn ($user) => mb_strtolower($user->email));
    }

    /**
     * @return array<string,int> set of refs that already exist (as keys)
     */
    private function existingExternalRefs(array $refs): array
    {
        $refs = array_values(array_unique(array_filter($refs, fn ($ref) => $ref !== '')));

        if ($refs === []) {
            return [];
        }

        // withTrashed: the unique index also covers soft-deleted tickets.
        return array_flip(
            Ticket::withTrashed()
                ->whereIn('external_ref', $refs)
                ->pluck('external_ref')
                ->all()
        );
    }

    /**
     * @return array{priority:Priority,status:TicketStatus,created_at:?string}
     */
    private function validateRow(array $d, Collection $customers, Collection $categories, Collection $agents): array
    {
        if ($d['external_ref'] === '') {
            throw new \Exception('external_ref is required');
        }

        if ($d['subject'] === '') {
            throw new \Exception('Subject is required');
        }

        if ($d['description'] === '') {
            throw new \Exception('Description is required');
        }

        if ($d['priority'] === '') {
            throw new \Exception('Priority is required');
        }

        $priority = Priority::tryFrom(mb_strtolower($d['priority']));
        if ($priority === null) {
            throw new \Exception("Invalid priority: {$d['priority']}");
        }

        if ($d['category'] === '') {
            throw new \Exception('Category is required');
        }

        if (! $categories->has(mb_strtolower($d['category']))) {
            throw new \Exception("Category not found: {$d['category']}");
        }

        if ($d['status'] === '') {
            throw new \Exception('Status is required');
        }

        $status = TicketStatus::tryFrom(mb_strtolower($d['status']));
        if ($status === null) {
            throw new \Exception("Invalid status: {$d['status']}");
        }

        if ($d['customer_email'] === '') {
            throw new \Exception('Customer email is required');
        }

        if (! $customers->has(mb_strtolower($d['customer_email']))) {
            throw new \Exception("Customer not found: {$d['customer_email']}");
        }

        if ($d['assigned_agent_email'] !== '' && ! $agents->has(mb_strtolower($d['assigned_agent_email']))) {
            throw new \Exception("Assigned agent not found: {$d['assigned_agent_email']}");
        }

        $createdAt = null;

        if ($d['created_at'] !== '') {
            $parsed = \DateTime::createFromFormat('Y-m-d H:i:s', $d['created_at']);
            $errors = \DateTime::getLastErrors();

            if ($parsed === false
                || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                throw new \Exception("Invalid created_at format: {$d['created_at']}");
            }

            $createdAt = $parsed->format('Y-m-d H:i:s');
        }

        return [
            'priority' => $priority,
            'status' => $status,
            'created_at' => $createdAt,
        ];
    }

    /**
     * Raw column => value row for a bulk insert. Mirrors what Ticket::create()
     * stored before (enums as their value, updated_at = now, created_at from
     * the CSV when given).
     */
    private function ticketRow(array $d, array $parsed, string $ticketNumber, string $now, Collection $customers, Collection $categories, Collection $agents): array
    {
        $agentEmail = mb_strtolower($d['assigned_agent_email']);
        [$dueAt, $warningAt] = $this->slaFor($parsed['priority']);

        return [
            'ticket_number' => $ticketNumber,
            'subject' => $d['subject'],
            'description' => $d['description'],
            'priority' => $parsed['priority']->value,
            'status' => $parsed['status']->value,
            'category_id' => $categories->get(mb_strtolower($d['category']))->id,
            'created_by' => $customers->get(mb_strtolower($d['customer_email']))->id,
            'assigned_to' => $agentEmail !== '' ? $agents->get($agentEmail)->id : null,
            'due_at' => $dueAt,
            'sla_warning_at' => $warningAt,
            'external_ref' => $d['external_ref'],
            'created_at' => $parsed['created_at'] ?? $now,
            'updated_at' => $now,
        ];
    }

    /**
     * SLA depends only on the priority, so compute it once per priority per
     * import (measured from the import start) instead of twice per row.
     *
     * @return array{0:string,1:string} [due_at, sla_warning_at]
     */
    private function slaFor(Priority $priority): array
    {
        $this->slaStart ??= now();

        return $this->slaCache[$priority->value] ??= [
            $this->slaService->calculateDue($priority, $this->slaStart)->format('Y-m-d H:i:s'),
            $this->slaService->calculateWarning($priority, $this->slaStart)->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param  array<int,array{0:int,1:array,2:string}>  $errors
     */
    private function recordErrors(Import $import, array $errors): void
    {
        if ($errors === []) {
            return;
        }

        usort($errors, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $now = now();

        ImportError::insert(array_map(fn (array $error): array => [
            'import_id' => $import->id,
            'row_number' => $error[0],
            'raw_data' => json_encode($error[1], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'error_message' => $error[2],
            'created_at' => $now,
            'updated_at' => $now,
        ], $errors));
    }

    private function updateImportStatus(Import $import): void
    {
        if ($import->failed_rows === 0) {
            $status = 'completed';
        } elseif ($import->imported_rows === 0) {
            $status = 'failed';
        } else {
            $status = 'completed_with_errors';
        }

        $import->update(['status' => $status, 'finished_at' => now()]);
    }
}
