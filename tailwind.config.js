/** @type {import("tailwindcss").Config} */
module.exports = {
  content: ["./templates/**/*.html.twig", "./assets/**/*.js"],
  theme: {
    extend: {
      colors: {
        regal: "#1E3A8A",
        navy: "#0F172A",
        canvas: "#F8FAFC",
      },
      fontFamily: {
        sans: ["system-ui", "-apple-system", "Segoe UI", "Roboto", "Helvetica Neue", "Arial", "sans-serif"],
      },
      maxWidth: {
        "7xl": "1280px",
      },
    },
  },
  plugins: [],
};
