import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/js/app.js",
                "resources/scss/app.scss",
                "resources/scss/admin/style.scss",
            ],
            refresh: true,
        }),
    ],
});
