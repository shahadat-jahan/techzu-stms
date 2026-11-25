import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/inline-reply.js', 'resources/js/ticket-customer-typeahead.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
