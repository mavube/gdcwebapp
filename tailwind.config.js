/** @type {import("tailwindcss").Config} */
module.exports = {
  content: ["./templates/**/*.html.twig", "./assets/**/*.js"],
  theme: {
    extend: {
      colors: {
        // Nova palette. The three original token names are kept because the
        // templates and assets/funnel.js depend on them; their values now carry
        // the design's warm surface, near-black ink and copper accent.
        regal: "#b98b6e", // copper accent
        navy: "#161616", // ink
        canvas: "#fffdf9", // warm off-white
        ember: "#a3765b", // copper, pressed/hover
        cream: "#f4f0e9",
        sand: "#eee8df",
        linen: "#ddd4c9",
        taupe: "#b9aea2",
        muted: "#68635d",
        danger: "#b42318",
        ok: "#2f6b4f",
      },
      fontFamily: {
        // Nova self-hosts Satoshi (body) and Outfit (headings). No font binary
        // is committed, so these resolve only once the owner adds the files
        // after clearing their licences; until then the stack falls through to
        // the system faces.
        sans: ["Satoshi", "system-ui", "-apple-system", "Segoe UI", "Roboto", "Helvetica Neue", "Arial", "sans-serif"],
        heading: ["Outfit", "Satoshi", "system-ui", "-apple-system", "Segoe UI", "Roboto", "Helvetica Neue", "Arial", "sans-serif"],
      },
      fontSize: {
        display: ["clamp(2.75rem, 7vw, 4rem)", { lineHeight: "0.96", letterSpacing: "-0.055em" }],
        headline: ["clamp(2rem, 4.5vw, 2.625rem)", { lineHeight: "0.98", letterSpacing: "-0.05em" }],
        title: ["1.25rem", { lineHeight: "1.3", letterSpacing: "-0.01em" }],
        label: ["0.6875rem", { lineHeight: "1.2", letterSpacing: "0.18em" }],
      },
      borderRadius: {
        field: "0.75rem",
        card: "1.5rem",
        panel: "2rem",
      },
      maxWidth: {
        "7xl": "1280px",
        measure: "68ch",
      },
      boxShadow: {
        soft: "0 14px 40px -24px rgb(22 22 22 / 0.28)",
        strong: "0 28px 80px -32px rgb(22 22 22 / 0.38)",
      },
    },
  },
  plugins: [],
};
