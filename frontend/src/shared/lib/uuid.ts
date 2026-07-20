/**
 * `crypto.randomUUID()` is only exposed by browsers in a secure context
 * (HTTPS or `localhost`). This app can be deployed over plain HTTP, where
 * that API is `undefined` and calling it throws
 * `TypeError: crypto.randomUUID is not a function`. `crypto.getRandomValues`
 * has no such restriction, so we use it to build a RFC4122 v4 UUID whenever
 * `randomUUID` is unavailable, falling back to `Math.random` as a last resort
 * for very old environments without `crypto` support at all.
 */
export function createUuid(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }

  if (typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function') {
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;

    const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
    return [
      hex.slice(0, 8),
      hex.slice(8, 12),
      hex.slice(12, 16),
      hex.slice(16, 20),
      hex.slice(20, 32),
    ].join('-');
  }

  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
    const random = (Math.random() * 16) | 0;
    const value = char === 'x' ? random : (random & 0x3) | 0x8;
    return value.toString(16);
  });
}
