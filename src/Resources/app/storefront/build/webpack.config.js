const { join } = require('path');

module.exports = () => {
    return {
        output: {
            path: join(__dirname, '../dist/storefront/js'),
            filename: '[name]/[name].js',
        }
    };
};
