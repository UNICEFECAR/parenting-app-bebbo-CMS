/* eslint-disable no-underscore-dangle */
const path = require('path');
const webpack = require('webpack');
const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const _MiniCssExtractPlugin = require('mini-css-extract-plugin');
const _RemovePlugin = require('remove-files-webpack-plugin');
const _ImageminPlugin = require('imagemin-webpack-plugin').default;
const glob = require('glob');

const imagePath = path.resolve(__dirname, '../images');

const MiniCssExtractPlugin = new _MiniCssExtractPlugin({
  filename: '[name].css',
  chunkFilename: '[id].css',
});

const RemovePlugin = new _RemovePlugin({
  /**
   * After compilation permanently remove empty JS files created from CSS entries.
   */
  before: {
    // parameters for "before normal compilation" stage.
  },
  watch: {
    // parameters for "before watch compilation" stage.
  },
  after: {
    test: [
      {
        folder: 'dist',
        method: (absoluteItemPath) => {
          return new RegExp(/\.js.*$/, 'm').test(absoluteItemPath);
        },
      },
    ],
  },
});

const ImageminPlugin = new _ImageminPlugin({
  disable: process.env.NODE_ENV !== 'production',
  externalImages: {
    context: imagePath,
    sources: glob.sync(path.resolve(imagePath, '**/*.{png,jpg,gif,svg}')),
    destination: imagePath,
  },
});

const ProgressPlugin = new webpack.ProgressPlugin();

module.exports = {
  ProgressPlugin,
  MiniCssExtractPlugin,
  RemovePlugin,
  ImageminPlugin,
  CleanWebpackPlugin: new CleanWebpackPlugin({
    cleanOnceBeforeBuildPatterns: ['!*.{png,jpg,gif,svg}'],
    cleanAfterEveryBuildPatterns: ['remove/**', '!js', '!*.{png,jpg,gif,svg}'],
  }),
};
