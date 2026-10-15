const fs = require('fs');
const postcss = require('postcss');
const nestedModule = require('postcss-nested');
const nested = nestedModule.default || nestedModule;

// Args: input.css output.css
const input = process.argv[2];
const output = process.argv[3];

if (!input || !output) {
    console.error("Usage: node compile-css.js <input.css> <output.css>");
    process.exit(1);
}

const css = fs.readFileSync(input, 'utf8');

postcss([nested])
    .process(css, { from: input, to: output })
    .then(result => {
        fs.writeFileSync(output, result.css);
    })
    .catch(err => {
        console.error(err);
        process.exit(1);
    });
