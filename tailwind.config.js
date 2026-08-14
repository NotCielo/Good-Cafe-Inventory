/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./**/*.php",
    "!./node_modules/**/*",
  ],
  theme: {
    extend: {
      colors: {
        cafe: {
          50:  "#f3faec",
          100: "#e2f4cf",
          200: "#c8e9a3",
          300: "#a6da6d",
          400: "#89c942",
          500: "#61b425",
          600: "#4a8f1b",
          700: "#3a6f17",
          800: "#2f5817",
          900: "#284a17",
        },
        ink: {
          50:  "#f6f7f5",
          100: "#e7e9e4",
          200: "#c9cec2",
          300: "#a3ab97",
          400: "#78826b",
          500: "#5a634e",
          600: "#464d3c",
          700: "#383d31",
          800: "#282b23",
          900: "#1c1e18",
        },
      },
      fontFamily: {
        display: ["'Baloo 2'", "'Segoe UI Rounded'", "ui-rounded", "system-ui", "sans-serif"],
        body: ["'Inter'", "system-ui", "-apple-system", "sans-serif"],
      },
      borderRadius: {
        xl2: "1.25rem",
      },
      boxShadow: {
        soft: "0 2px 10px -2px rgba(40, 74, 23, 0.12)",
        card: "0 4px 20px -4px rgba(40, 74, 23, 0.18)",
      },
    },
  },
  plugins: [],
};
