import "./bootstrap";
import "antd/dist/reset.css";
import "bootstrap/dist/js/bootstrap.bundle.min.js";
import "../css/app.css";

import { createRoot } from "react-dom/client";
import { createInertiaApp } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createIcons, icons } from "lucide";

// Initialize Lucide icons when the DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
    createIcons({
        icons: icons,
    });
});

const appName = "Diễn đàn học sinh Chuyên Biên Hòa"; // Your base app name

createInertiaApp({
    title: (title) => {
        if (title && title !== appName) {
            return `${title} - ${appName}`;
        }
        return appName; // Default title for home or pages without a specific title
    },
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob("./Pages/**/*.jsx")
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: "#319528",
    },
});
