const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
    ...defaultConfig,
    entry: {
        'gls-shipping-blocks-frontend': path.resolve(process.cwd(), 'src', 'gls-shipping-blocks-frontend.js'),
        'gls-shipping-blocks-editor': path.resolve(process.cwd(), 'src', 'gls-shipping-blocks-editor.js'),
    },
    output: {
        path: path.resolve(process.cwd(), 'build'),
        filename: '[name].js',
    },
    externals: {
        ...defaultConfig.externals,
        '@woocommerce/blocks-checkout': ['wc', 'blocksCheckout'],
    },
};
