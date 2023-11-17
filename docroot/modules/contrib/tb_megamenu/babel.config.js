module.exports = (api) => {
  api.cache(true);

  const presets = [['@babel/preset-env']];

  const comments = false;

  return { presets, comments };
};
