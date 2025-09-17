import defaultTheme from "tailwindcss/defaultTheme";
import forms from "@tailwindcss/forms";
const { TinyColor } = require("@ctrl/tinycolor");

function genPalette(base) {
  const c = new TinyColor(base);
  return {
    DEFAULT: c.toHexString(),
    light: c.lighten(30).toHexString(),
    dark: c.darken(30).toHexString(),
    muted: c.desaturate(20).toHexString(),
    50: c.lighten(45).toHexString(),
    100: c.lighten(40).toHexString(),
    200: c.lighten(30).toHexString(),
    300: c.lighten(20).toHexString(),
    400: c.lighten(10).toHexString(),
    500: c.toHexString(),
    600: c.darken(10).toHexString(),
    700: c.darken(20).toHexString(),
    800: c.darken(30).toHexString(),
    900: c.darken(40).toHexString(),
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
      colors: {
        primary: genPalette("#319527"), // brand color
      },
      fontFamily: {
        sans: ["Inter", ...defaultTheme.fontFamily.sans],
        inter: ["Inter", ...defaultTheme.fontFamily.sans],
      },
    },
  },

  plugins: [forms],
};
