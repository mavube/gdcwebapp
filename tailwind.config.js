/** @type {import("tailwindcss").Config} */
module.exports = {
  content: ["./templates/**/*.html.twig", "./assets/**/*.js"],
  theme: {
    extend: {
      colors: {
        // GDC brand palette, taken from the live site's Elementor global kit:
        // primary #0181C9, secondary #1A2E38, text/accent #EC7600.
        //
        // The legacy names regal/navy/canvas are kept as aliases because
        // assets/funnel.js and the existing templates reference them. New work
        // should prefer the semantic names (brand/ink/surface/accent).
        brand: {
          DEFAULT: "#0181C9", // GDC blue - primary actions, links
          deep: "#016BA6", // pressed blue
          ink: "#1A2E38", // deep teal-navy - headings, dark surfaces
        },
        accent: {
          DEFAULT: "#EC7600", // GDC orange - eyebrows, highlights
          deep: "#C96300", // pressed orange
        },
        surface: "#FFFFFF",
        mist: "#F4F7FA", // very light blue-grey section wash
        slate: {
          50: "#F8FAFC",
          100: "#EEF2F6",
          200: "#DCE3EA",
          400: "#93A2B0",
          600: "#5A6B7A",
          800: "#1A2E38", // aliases the brand ink so slate-800/20 keeps working
        },

        // Legacy aliases. Kept so existing templates and funnel.js keep
        // resolving; values now carry the GDC palette.
        regal: "#0181C9", // was the copper accent; now GDC blue
        navy: "#1A2E38", // was near-black ink; now brand deep teal-navy
        canvas: "#FFFFFF", // was warm off-white; now white
        ember: "#016BA6", // pressed blue
        cream: "#F4F7FA",
        sand: "#EEF2F6",
        linen: "#DCE3EA",
        taupe: "#93A2B0",
        muted: "#5A6B7A",
        danger: "#B42318",
        ok: "#2F6B4F",
      },
      fontFamily: {
        // Self-hosted, SIL OFL licensed, latin + latin-ext subsets in
        // public/fonts. No CDN request: see @font-face in assets/styles/app.css.
        sans: ["Manrope", "system-ui", "-apple-system", "Segoe UI", "Roboto", "Helvetica Neue", "Arial", "sans-serif"],
        heading: ["Plus Jakarta Sans", "Manrope", "system-ui", "-apple-system", "Segoe UI", "Roboto", "Helvetica Neue", "Arial", "sans-serif"],
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
