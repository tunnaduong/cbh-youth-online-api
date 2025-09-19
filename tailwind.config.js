import defaultTheme from "tailwindcss/defaultTheme";
import forms from "@tailwindcss/forms";
const { TinyColor } = require("@ctrl/tinycolor");

const green = {
  50: "#f3fbf2",
  100: "#e3f8e0",
  200: "#c7f0c2",
  300: "#9ae392",
  400: "#65cd5b",
  500: "#319527",
  600: "#287421",
  700: "#245c1f",
  800: "#1e4c1b",
  900: "#0b290a",
};

function genPalette(base) {
  const c = new TinyColor(base);
  return {
    DEFAULT: c.toHexString(),
    light: c.lighten(30).toHexString(),
    dark: c.darken(30).toHexString(),
    muted: c.desaturate(20).toHexString(),
    50: green[50],
    100: green[100],
    200: green[200],
    300: green[300],
    400: green[400],
    500: green[500],
    600: green[600],
    700: green[700],
    800: green[800],
    900: green[900],
    950: green[950],
  };
}

/** @type {import('tailwindcss').Config} */
export default {
  darkMode: "class",
  content: [
    "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
    "./storage/framework/views/*.php",
    "./resources/views/**/*.blade.php",
    "./resources/js/**/*.jsx",
  ],

  theme: {
    extend: {
      ringColor: {
        DEFAULT: "#c7f0c2",
      },
      colors: {
        primary: genPalette("#319527"), // brand color
        gray: {
          600: "#585858",
          700: "#3C3C3C",
        },
      },
      fontFamily: {
        sans: ["Inter", ...defaultTheme.fontFamily.sans],
        inter: ["Inter", ...defaultTheme.fontFamily.sans],
      },
      typography: {
        DEFAULT: {
          css: {
            h2: {
              fontSize: "1.2rem", // tương đương ~28px
              fontWeight: "700",
              lineHeight: "1.3",
              marginTop: "1em",
              marginBottom: "0.5em",
            },
            hr: {
              borderColor: "#919191", // màu xám nhạt
            },
          },
        },
      },
    },
  },

  plugins: [forms, require("@tailwindcss/typography")],
};
