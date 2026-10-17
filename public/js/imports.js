// Import progress polling and UI management
class ImportProgressManager {
    constructor(options) {
        this.importId = options.importId;
        this.progressEndpoint = options.progressEndpoint || `/admin/imports/${this.importId}/progress`;
        this.failedCsvEndpoint = options.failedCsvEndpoint || `/admin/imports/${this.importId}/failed-csv`;
        this.showEndpoint = options.showEndpoint || `/admin/imports/${this.importId}`;
        this.pollInterval = options.pollInterval || 2000;
        this.onProgressUpdate = options.onProgressUpdate || this.defaultProgressUpdate;
        this.onComplete = options.onComplete || this.defaultComplete;
        this.onError = options.onError || this.defaultError;

        this.polling = null;
        this.isComplete = false;
    }

    start() {
        if (this.polling) {
            clearInterval(this.polling);
        }

        this.isComplete = false;
        this.polling = setInterval(() => this.pollProgress(), this.pollInterval);
        this.pollProgress();
    }

    stop() {
        if (this.polling) {
            clearInterval(this.polling);
            this.polling = null;
        }
    }

    async pollProgress() {
        try {
            const response = await fetch(this.progressEndpoint);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            this.onProgressUpdate(data);

            if (data.status === 'completed' || data.status === 'completed_with_errors' || data.status === 'failed') {
                this.isComplete = true;
                this.stop();
                this.onComplete(data);
            }
        } catch (error) {
            console.error('Error polling import progress:', error);
            this.onError(error);
        }
    }

    defaultProgressUpdate(data) {
        this.updateProgressBar(data);
        this.updateStatusBadge(data.status);
        this.updateCounts(data);
    }

    defaultComplete(data) {
        console.log('Import completed:', data);
        this.showCompletionMessage(data);
    }

    defaultError(error) {
        console.error('Import error:', error);
        this.showErrorMessage(error);
    }

    updateProgressBar(data) {
        const progressBar = document.getElementById('progress-bar');
        const progressText = document.getElementById('progress-text');

        if (progressBar && progressText) {
            const percentage = data.total > 0 ? (data.processed / data.total) * 100 : 0;
            progressBar.style.width = `${percentage}%`;
            progressText.textContent = `${data.processed}/${data.total} rows processed`;
        }
    }

    updateStatusBadge(status) {
        const statusBadge = document.getElementById('status-badge');

        if (statusBadge) {
            statusBadge.className = this.getStatusBadgeClass(status);
            statusBadge.textContent = this.getStatusLabel(status);
        }
    }

    updateCounts(data) {
        const importedCount = document.getElementById('imported-count');
        const failedCount = document.getElementById('failed-count');
        const processedCount = document.getElementById('processed-count');

        if (importedCount) importedCount.textContent = data.imported;
        if (failedCount) failedCount.textContent = data.failed;
        if (processedCount) processedCount.textContent = data.processed;
    }

    getStatusBadgeClass(status) {
        const classes = {
            'pending': 'bg-yellow-100 text-yellow-800',
            'processing': 'bg-blue-100 text-blue-800',
            'completed': 'bg-green-100 text-green-800',
            'completed_with_errors': 'bg-orange-100 text-orange-800',
            'failed': 'bg-red-100 text-red-800',
        };

        return classes[status] || 'bg-gray-100 text-gray-800';
    }

    getStatusLabel(status) {
        const labels = {
            'pending': 'Pending',
            'processing': 'Processing',
            'completed': 'Completed',
            'completed_with_errors': 'Completed with Errors',
            'failed': 'Failed',
        };

        return labels[status] || status;
    }

    showCompletionMessage(data) {
        const completionMessage = document.getElementById('completion-message');
        const completionDetails = document.getElementById('completion-details');

        if (completionMessage && completionDetails) {
            completionMessage.classList.remove('hidden');
            completionDetails.textContent = this.getCompletionMessage(data);

            if (data.failed > 0) {
                this.showDownloadButton();
            }
        }
    }

    showErrorMessage(error) {
        const errorMessage = document.getElementById('error-message');

        if (errorMessage) {
            errorMessage.textContent = `Error updating progress: ${error.message}`;
            errorMessage.classList.remove('hidden');
        }
    }

    showDownloadButton() {
        const downloadButton = document.getElementById('download-failed-csv');

        if (downloadButton) {
            downloadButton.classList.remove('hidden');
        }
    }

    getCompletionMessage(data) {
        if (data.status === 'completed') {
            return `Import completed successfully! ${data.imported} rows were imported.`;
        } else if (data.status === 'completed_with_errors') {
            return `Import completed with errors. ${data.imported} rows imported, ${data.failed} rows failed.`;
        } else if (data.status === 'failed') {
            return 'Import failed. Please check the error details.';
        }

        return 'Import completed.';
    }
}

// Initialize import progress monitoring
function initializeImportProgress() {
    const importId = document.body.dataset.importId;

    if (!importId) {
        return;
    }

    const progressManager = new ImportProgressManager({
        importId: importId,
        onProgressUpdate: (data) => {
            updateProgressDisplay(data);
        },
        onComplete: (data) => {
            showCompletionNotification(data);
        },
        onError: (error) => {
            console.error('Import progress error:', error);
        },
    });

    // Start polling
    progressManager.start();

    // Clean up on page unload
    window.addEventListener('beforeunload', () => {
        progressManager.stop();
    });
}

function updateProgressDisplay(data) {
    // Update progress bar
    const progressBar = document.getElementById('progress-bar');
    const progressText = document.getElementById('progress-text');

    if (progressBar && progressText) {
        const percentage = data.total > 0 ? (data.processed / data.total) * 100 : 0;
        progressBar.style.width = `${percentage}%`;
        progressText.textContent = `${data.processed}/${data.total} rows processed`;
    }

    // Update status badge
    const statusBadge = document.getElementById('status-badge');
    if (statusBadge) {
        statusBadge.className = getStatusBadgeClass(data.status);
        statusBadge.textContent = getStatusLabel(data.status);
    }

    // Update counts
    document.getElementById('imported-count').textContent = data.imported;
    document.getElementById('failed-count').textContent = data.failed;
    document.getElementById('processed-count').textContent = data.processed;
}

function showCompletionNotification(data) {
    const notification = document.getElementById('completion-notification');
    const notificationText = document.getElementById('completion-notification-text');

    if (notification && notificationText) {
        notificationText.textContent = getCompletionMessage(data);
        notification.classList.remove('hidden');

        // Auto-hide after 10 seconds
        setTimeout(() => {
            notification.classList.add('hidden');
        }, 10000);
    }
}

function getStatusBadgeClass(status) {
    const classes = {
        'pending': 'bg-yellow-100 text-yellow-800',
        'processing': 'bg-blue-100 text-blue-800',
        'completed': 'bg-green-100 text-green-800',
        'completed_with_errors': 'bg-orange-100 text-orange-800',
        'failed': 'bg-red-100 text-red-800',
    };

    return classes[status] || 'bg-gray-100 text-gray-800';
}

function getStatusLabel(status) {
    const labels = {
        'pending': 'Pending',
        'processing': 'Processing',
        'completed': 'Completed',
        'completed_with_errors': 'Completed with Errors',
        'failed': 'Failed',
    };

    return labels[status] || status;
}

function getCompletionMessage(data) {
    if (data.status === 'completed') {
        return `✅ Import completed successfully! ${data.imported} rows were imported.`;
    } else if (data.status === 'completed_with_errors') {
        return `⚠️ Import completed with errors. ${data.imported} rows imported, ${data.failed} rows failed.`;
    } else if (data.status === 'failed') {
        return '❌ Import failed. Please check the error details.';
    }

    return 'Import completed.';
}

// Manual retry functionality
function retryImport() {
    if (confirm('Are you sure you want to retry this import? This will restart the import process.')) {
        // In a real implementation, this would call an API endpoint to restart the import
        console.log('Retrying import...');
        // For now, just reload the page
        window.location.reload();
    }
}

// Download failed CSV
function downloadFailedCsv() {
    const importId = document.body.dataset.importId;
    const downloadUrl = `/admin/imports/${importId}/failed-csv`;

    // Create a temporary link and trigger download
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.download = `failed_import_${importId}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Initialize everything when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    initializeImportProgress();

    // Add event listeners for manual actions
    const retryButton = document.getElementById('retry-import');
    const downloadButton = document.getElementById('download-failed-csv');

    if (retryButton) {
        retryButton.addEventListener('click', retryImport);
    }

    if (downloadButton) {
        downloadButton.addEventListener('click', downloadFailedCsv);
    }
});