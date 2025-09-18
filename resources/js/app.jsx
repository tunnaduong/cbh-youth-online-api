import "./bootstrap";
import "antd/dist/reset.css";
import "bootstrap/dist/js/bootstrap.bundle.min.js";
import "../css/app.css";

import { createRoot } from "react-dom/client";
import { createInertiaApp } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createIcons, icons } from "lucide";
import { ThemeProvider, useTheme } from "./Contexts/themeContext";
import { ConfigProvider, theme as antdTheme } from "antd";

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
    resolvePageComponent(`./Pages/${name}.jsx`, import.meta.glob("./Pages/**/*.jsx")),
  setup({ el, App, props }) {
    const root = createRoot(el);

    root.render(
      <ThemeProvider>
        <ThemedApp App={App} props={props} />
      </ThemeProvider>
    );
  },
  progress: {
    color: "#319528",
  },
});

function ThemedApp({ App, props }) {
  const { theme } = useTheme();

  return (
    <ConfigProvider
      theme={{
        token: {
          fontFamily: "Inter, sans-serif",
          colorPrimary: "#319527",
        },
        components: {
          Input: {
            colorBgContainer: "transparent",
            colorBorder: theme === "dark" ? "#737373" : "#e5e7eb",
            colorTextPlaceholder: "#888",
          },
          Checkbox: {
            colorBgContainer: "transparent",
            colorBorder: theme === "dark" ? "#737373" : "#e5e7eb",
          },
          Button: {
            colorPrimary: "#319527",
            colorPrimaryHover: "#40b235",
            colorPrimaryActive: "#287421",
          },
        },
        algorithm: theme === "dark" ? antdTheme.darkAlgorithm : antdTheme.defaultAlgorithm,
      }}
    >
      <App {...props} />
    </ConfigProvider>
  );
}
