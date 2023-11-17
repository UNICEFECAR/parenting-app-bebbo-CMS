const path = require('path');
const glob = require('glob');
const loaders = require('./loaders');
const plugins = require('./plugins');

const webpackDir = path.resolve(__dirname);
const rootDir = path.resolve(__dirname, '..');
const distDir = path.resolve(rootDir, 'dist');

function getEntries(pattern) {
  const entries = {};

  glob.sync(pattern).forEach((file) => {
    const filePath = file.split('js/')[1];
    const newfilePath = `js/${filePath.replace('.js', '')}`;
    entries[newfilePath] = file;
  });

  entries.admin = path.resolve(webpackDir, 'admin.js');
  entries.base = path.resolve(webpackDir, 'base.js');
  entries.styles = path.resolve(webpackDir, 'styles.js');

  return entries;
}

module.exports = {
  stats: {
    errorDetails: true,
  },
  entry: getEntries(
    path.resolve(rootDir, 'js/**/!(*.component|*.min|*.test).js'),
  ),
  module: {
    rules: [loaders.CSSLoader, loaders.ImageLoader, loaders.JSLoader],
  },
  plugins: [
    plugins.MiniCssExtractPlugin,
    plugins.RemovePlugin,
    plugins.ImageminPlugin,
    plugins.ProgressPlugin,
    plugins.CleanWebpackPlugin,
  ],
  output: {
    path: distDir,
    filename: '[name].js',
  },
  externals: {
    jquery: 'jQuery',
  },
};
