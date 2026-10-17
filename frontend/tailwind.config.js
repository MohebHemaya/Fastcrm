import { heroui } from "@heroui/react";

/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
    "./node_modules/@heroui/theme/dist/**/*.{js,ts,jsx,tsx,mjs}",
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ["Cairo", "Inter", "system-ui", "-apple-system", "sans-serif"],
      },
      fontSize: {
        tiny: "0.75rem",
        small: "0.875rem",
        medium: "1rem",
        large: "1.125rem",
      },
      colors: {
        fast: {
          primary: "#7D0E40",
          hover: "#681035",
          accent: "#9a1a50",
          secondary: "#5A5E60",
          muted: "#7a7f82",
          bg: "#F7F7F7",
          surface: "#FFFFFF",
          border: "#E8E8E8",
        },
      },
    },
  },
  darkMode: "class",
  plugins: [
    heroui({
      themes: {
        light: {
          colors: {
            primary: {
              50: "#fdf2f6",
              100: "#fbe6ee",
              200: "#f7cfde",
              300: "#f0a8c2",
              400: "#e4769f",
              500: "#7D0E40",
              600: "#681035",
              700: "#540c2a",
              800: "#420a21",
              900: "#33081a",
              DEFAULT: "#7D0E40",
              foreground: "#FFFFFF",
            },
            secondary: {
              50: "#f6f7f7",
              100: "#eceeee",
              200: "#d9ddde",
              300: "#bfc5c7",
              400: "#8e9699",
              500: "#5A5E60",
              600: "#4a4d4f",
              700: "#3c3e40",
              800: "#2f3132",
              900: "#232425",
              DEFAULT: "#5A5E60",
              foreground: "#FFFFFF",
            },
            focus: "#7D0E40",
          },
        },
        dark: {
          colors: {
            primary: {
              50: "#33081a",
              100: "#420a21",
              200: "#540c2a",
              300: "#681035",
              400: "#7D0E40",
              500: "#9a1a50",
              600: "#b82963",
              700: "#d1437b",
              800: "#e4769f",
              900: "#f0a8c2",
              DEFAULT: "#9a1a50",
              foreground: "#FFFFFF",
            },
            secondary: {
              DEFAULT: "#7a7f82",
              foreground: "#FFFFFF",
            },
            focus: "#9a1a50",
          },
        },
      },
      layout: {
        radius: {
          small: "8px",
          medium: "12px",
          large: "16px",
        },
        borderWidth: {
          small: "1px",
          medium: "1px",
          large: "1px",
        },
        fontSize: {
          tiny: "0.75rem",
          small: "0.875rem",
          medium: "1rem",
          large: "1.125rem",
        },
      },
    }),
  ],
};
