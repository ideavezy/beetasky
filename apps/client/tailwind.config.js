/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Poppins', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'Arial', 'sans-serif'],
        mono: ['JetBrains Mono', 'SF Mono', 'Fira Code', 'monospace'],
      },
      animation: {
        'fadeIn': 'fadeIn 0.4s ease-in-out forwards',
        'fadeOut': 'fadeOut 0.4s ease-in-out forwards',
        'slideInFromRight': 'slideInFromRight 0.5s ease-out forwards',
        'slideInFromLeft': 'slideInFromLeft 0.5s ease-out forwards',
      },
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0', transform: 'translateY(10px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        fadeOut: {
          '0%': { opacity: '1', transform: 'translateY(0)' },
          '100%': { opacity: '0', transform: 'translateY(10px)' },
        },
        slideInFromRight: {
          '0%': { opacity: '0', transform: 'translateX(30px)' },
          '100%': { opacity: '1', transform: 'translateX(0)' },
        },
        slideInFromLeft: {
          '0%': { opacity: '0', transform: 'translateX(-30px)' },
          '100%': { opacity: '1', transform: 'translateX(0)' },
        },
      },
    },
  },
  plugins: [require("daisyui")],
  daisyui: {
    themes: [
      {
        dark: {
          // Background (Dark Theme - Default)
          "base-100": "oklch(32% 0.015 252.42)",    // Main background - dark blue-gray
          "base-200": "oklch(29% 0.014 253.1)",     // Secondary - slightly darker
          "base-300": "oklch(26% 0.012 254.09)",    // Tertiary - darkest
          
          // Text
          "base-content": "oklch(97.807% 0.029 256.847)",  // Primary text - near white
          
          // Primary (Golden/Amber - Main Actions)
          "primary": "oklch(82% 0.189 84.429)",
          "primary-content": "oklch(14% 0.005 285.823)",
          
          // Secondary (Pink/Magenta)
          "secondary": "oklch(65% 0.241 354.308)",
          "secondary-content": "oklch(94% 0.028 342.258)",
          
          // Accent (Teal/Cyan)
          "accent": "oklch(77% 0.152 181.912)",
          "accent-content": "oklch(38% 0.063 188.416)",
          
          // Neutral
          "neutral": "oklch(14% 0.005 285.823)",
          "neutral-content": "oklch(92% 0.004 286.32)",
          
          // Status Colors
          "info": "oklch(74% 0.16 232.661)",        // Blue
          "success": "oklch(76% 0.177 163.223)",    // Green
          "warning": "oklch(82% 0.189 84.429)",     // Amber (same as primary)
          "error": "oklch(71% 0.194 13.428)",       // Red
          
          // Border radius
          "--rounded-box": "0.5rem",
          "--rounded-btn": "0.375rem",
          "--rounded-badge": "1.5rem",
          
          // Animation
          "--animation-btn": "0.2s",
          "--animation-input": "0.2s",
          
          // Focus ring
          "--btn-focus-scale": "0.98",
        },
        light: {
          // Background (Light Theme)
          "base-100": "oklch(98% 0 240)",           // Main background - near white
          "base-200": "oklch(100% 0 240)",          // Secondary - pure white
          "base-300": "oklch(95% 0 240)",           // Tertiary - light gray
          
          // Text
          "base-content": "oklch(21% 0.006 285.885)",  // Primary text - near black
          
          // Primary (Golden/Amber - Main Actions)
          "primary": "oklch(72% 0.189 84.429)",
          "primary-content": "oklch(98% 0.005 285.823)",
          
          // Secondary (Pink/Magenta)
          "secondary": "oklch(55% 0.241 354.308)",
          "secondary-content": "oklch(98% 0.028 342.258)",
          
          // Accent (Teal/Cyan)
          "accent": "oklch(67% 0.152 181.912)",
          "accent-content": "oklch(98% 0.063 188.416)",
          
          // Neutral
          "neutral": "oklch(25% 0.005 285.823)",
          "neutral-content": "oklch(98% 0.004 286.32)",
          
          // Status Colors
          "info": "oklch(64% 0.16 232.661)",
          "success": "oklch(66% 0.177 163.223)",
          "warning": "oklch(72% 0.189 84.429)",
          "error": "oklch(61% 0.194 13.428)",
          
          // Border radius
          "--rounded-box": "0.5rem",
          "--rounded-btn": "0.375rem",
          "--rounded-badge": "1.5rem",
          
          // Animation
          "--animation-btn": "0.2s",
          "--animation-input": "0.2s",
          
          // Focus ring
          "--btn-focus-scale": "0.98",
        },
      },
    ],
    darkTheme: "dark",
  },
}

