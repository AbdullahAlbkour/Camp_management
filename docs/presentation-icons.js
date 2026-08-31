const React = require('react');
const { renderToStaticMarkup } = require('react-dom/server');
const sharp = require('sharp');
const Fa = require('react-icons/fa');

async function icon(name, hex, px = 256) {
  const Comp = Fa[name];
  if (!Comp) throw new Error('no icon ' + name);
  let svg = renderToStaticMarkup(React.createElement(Comp, { size: px }));
  svg = svg.replace(/currentColor/g, '#' + hex);
  if (!/width=/.test(svg)) svg = svg.replace('<svg', `<svg width="${px}" height="${px}"`);
  const buf = await sharp(Buffer.from(svg), { density: 384 }).resize(px, px, {
    fit: 'contain', background: { r: 0, g: 0, b: 0, alpha: 0 },
  }).png().toBuffer();
  return 'image/png;base64,' + buf.toString('base64');
}
module.exports = { icon };
