import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import react from "@vitejs/plugin-react";
import path from "path";
import fs from "fs";

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
  server: {
    host: true, // lắng nghe tất cả IP
    port: 5173, // port Vite
    hmr: {
      host: process.env.VITE_HMR_HOST || "localhost",
    },
  },
});
