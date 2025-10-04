import "./bootstrap";
import "antd/dist/reset.css";
import "../css/app.css";

import { createRoot } from "react-dom/client";
import { createInertiaApp } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createIcons, icons } from "lucide";
import { ThemeProvider, useTheme } from "./Contexts/themeContext";
import { TopUsersProvider } from "./Contexts/TopUsersContext";
import { ConfigProvider, theme as antdTheme } from "antd";
import { useState, useEffect } from "react";
import LoadingScreen from "./Components/LoadingScreen";
import viVN from "antd/locale/vi_VN";

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
  resolve: (name) => {
    const page = resolvePageComponent(`./Pages/${name}.jsx`, import.meta.glob("./Pages/**/*.jsx"));
    page.then((module) => {
      module.default.layout = module.default.layout || ((page) => <>{page}</>);
    });
    return page;
  },
  setup({ el, App, props }) {
    const root = createRoot(el);

    root.render(
      <ThemeProvider>
        <TopUsersProvider>
          <ThemedApp App={App} props={props} />
        </TopUsersProvider>
      </ThemeProvider>
    );
  },
  progress: {
    color: "#319528",
  },
});

function ThemedApp({ App, props }) {
  const { theme } = useTheme();
  const [isInitialLoading, setIsInitialLoading] = useState(true);

  // Check if loading should be hidden via environment variable
  const hideLoading = import.meta.env.VITE_HIDE_LOADING === "true";

  useEffect(() => {
    if (hideLoading) {
      setIsInitialLoading(false);
      return;
    }

    // Simulate initial loading time
    const timer = setTimeout(() => {
      setIsInitialLoading(false);
    }, 2500); // 2 seconds loading time

    return () => clearTimeout(timer);
  }, [hideLoading]);

  const configProviderProps = {
    locale: viVN,
    theme: {
      token: {
        fontFamily: "Inter, sans-serif",
        colorPrimary: "#319527",
        controlHeight: 40,
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
          defaultBg: theme === "dark" ? "#3C3C3C" : "#ffffff", // dark = gray-800, light = white
          colorPrimary: "#319527",
          colorPrimaryHover: "#40b235",
          colorPrimaryActive: "#287421",
          defaultShadow: "none", // bỏ bóng dưới
          primaryShadow: "none", // nếu dùng primary
          defaultHoverBg: theme === "dark" ? "#414642" : "#EBFFF5",
        },
        Modal: {
          colorBgElevated: theme === "dark" ? "#3c3c3c" : "#ffffff",
        },
        Select: {
          colorBgContainer: "transparent",
          colorBorder: theme === "dark" ? "#737373" : "#e5e7eb",
        },
        Radio: {
          colorBgContainer: "transparent",
          colorBorder: theme === "dark" ? "#737373" : "#e5e7eb",
        },
        DatePicker: {
          colorBgContainer: "transparent",
          colorBorder: theme === "dark" ? "#737373" : "#e5e7eb",
        },
        ColorPicker: {
          colorBorder: theme === "dark" ? "#737373" : "#e5e7eb",
        },
      },
      algorithm: theme === "dark" ? antdTheme.darkAlgorithm : antdTheme.defaultAlgorithm,
    },
  };

  return (
    <ConfigProvider {...configProviderProps}>
      {hideLoading ? (
        <App {...props} />
      ) : (
        <LoadingScreen isLoading={isInitialLoading}>
          <App {...props} />
        </LoadingScreen>
      )}
    </ConfigProvider>
  );
}
