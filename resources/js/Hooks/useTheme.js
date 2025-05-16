import { useState, useEffect } from "react";

export function useTheme() {
    const [theme, setTheme] = useState(() => {
        // Check localStorage first
        const savedTheme = localStorage.getItem("theme");
        if (savedTheme) {
            return savedTheme;
        }

        // Check system preference
        if (window.matchMedia("(prefers-color-scheme: dark)").matches) {
            return "dark";
        }

        return "light";
    });

    useEffect(() => {
        // Update body class and localStorage when theme changes
        if (theme === "dark") {
            document.body.classList.add("dark");
        } else {
            document.body.classList.remove("dark");
        }
        localStorage.setItem("theme", theme);
    }, [theme]);

    // Function to toggle theme
    const toggleTheme = () => {
        setTheme((prevTheme) => (prevTheme === "light" ? "dark" : "light"));
    };

    return { theme, toggleTheme };
}
