import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import react from "@vitejs/plugin-react";
import path from "path"; // thêm import này

export default defineConfig({
  plugins: [
    laravel({
      input: ["resources/css/app.css", "resources/js/app.jsx"],
      refresh: true,
      publicDirectory: "public",
    }),
    react(),
  ],
  resolve: {
    alias: {
      "~bootstrap": "node_modules/bootstrap",
      "@": path.resolve(__dirname, "resources/js"), // dùng path.resolve
      "@assets": path.resolve(__dirname, "public"),
    },
  },
});
