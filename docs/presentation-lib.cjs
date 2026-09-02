// ---------- palette ----------
const P = {
  DARK: '0A2E36', DARK2: '113E48', DARK3: '17505C',
  TEAL: '02717D', SEA: '00A896', AMBER: 'E9A13B', RED: 'C2603F',
  LIGHT: 'F5F8F8', CARD: 'FFFFFF', INK: '16292E', MUTED: '5C7378',
  WHITE: 'FFFFFF', PALE: 'BFD4D7', LINE: 'DCE6E7',
  TINT_A: 'FDF3E3', TINT_T: 'ECF4F4',
};
const F = 'Arial', MONO = 'Courier New';
const W = 13.333, H = 7.5, M = 0.6;

// Bidi guard: a Latin run sitting inside Arabic prose drags the neutral
// punctuation around it (brackets, colons, plus signs) to the wrong side.
// Wrapping each Latin run in an isolate pins it in place.
const LRI = '⁦', PDI = '⁩';
function ar(t) {
  if (typeof t !== 'string' || !/[؀-ۿ]/.test(t)) return t;
  return t.replace(/[A-Za-z][A-Za-z0-9._#\-]*(?: [A-Za-z0-9][A-Za-z0-9._#\-]*)*/g, (m) => LRI + m + PDI);
}
const rtl = (o) => Object.assign({ isTextBox: true, rtlMode: true, align: 'right', fontFace: F, margin: 0 }, o);
const ltr = (o) => Object.assign({ isTextBox: true, align: 'left', fontFace: F, margin: 0 }, o);
const shadow = () => ({ type: 'outer', angle: 90, blur: 10, offset: 2, color: '0A2E36', opacity: 0.10 });

module.exports = { P, F, MONO, W, H, M, ar, rtl, ltr, shadow };
