import { useTheme } from "@/Contexts/themeContext";
import { Sun, Moon } from "lucide-react";

export default function DarkmodeToggle({ mobile = false }) {
  const { theme, toggleTheme } = useTheme();

  return (
    <>
      <style
        dangerouslySetInnerHTML={{
          __html:
            "\n /* Transition styles */\n .theme-transition {\n transition: background-color 0.3s, color 0.3s, border-color 0.3s;\n }\n\n /* Dark mode styles */\n .dark {\n background-color: #2c2f2e;\n color: #f3f4f6;\n }\n\n /* Toggle transition */\n .toggle-circle {\n transition: transform 0.3s ease;\n }\n\n .dark .toggle-circle {\n transform: translateX(1.4rem);\n }\n\n .logo-white:is(.dark *) {\n filter: brightness(0) saturate(100%) invert(100%) sepia(50%) saturate(258%) hue-rotate(319deg) brightness(126%) contrast(96%);\n }\n\n .theme-toggle {\n zoom: 0.75;\n }\n",
        }}
      />
      <button
        onClick={toggleTheme}
        className={`theme-toggle relative ${
          mobile ?? false ? "block xl:hidden" : "hidden xl:block"
        } h-8 w-14 rounded-full border !border-neutral-500 dark:border-neutral-500 bg-gray-100 dark:bg-neutral-700 hover:!border-green-600 theme-transition`}
        aria-label="Toggle theme"
      >
        <div className="toggle-circle absolute top-[3px] left-1 flex h-6 w-6 items-center justify-center rounded-full bg-white dark:!bg-black shadow-sm dark:transform dark:translate-x-6 transition-transform duration-300">
          {/* Sun icon for light mode */}
          {theme === "light" && <Sun className="h-3.5 w-3.5 text-black" />}
          {/* Moon icon for dark mode */}
          {theme === "dark" && <Moon className="h-3.5 w-3.5 text-black dark:!text-white" />}
        </div>
        <span className="sr-only">Toggle theme</span>
      </button>
    </>
  );
}
