const { merge } = require('webpack-merge');
const common = require('./webpack.common');
const plugins = require('./plugins');

module.exports = merge(common, {
  mode: 'development',
  devtool: 'source-map',
});
