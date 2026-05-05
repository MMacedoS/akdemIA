/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/views/**/index.blade.php',
        './resources/views/**/create.blade.php',
        './resources/views/**/_form.blade.php',
        './resources/views/**/edit.blade.php',
        './resources/views/**/show.blade.php',
    ],
    theme: {
        extend: {},
    },
    plugins: [],
};

