// Chat messages, comments and task descriptions are plain text with a few tags (inherited from Bitrix and the website):
//   [img]URL[/img]   [file name="x.pdf"]URL[/file]   [voice dur="0:12"]URL[/voice]   [disk file id=n123 ...]
//   [b] [i] [url=…]label[/url] [USER=5]Name[/USER] [TIMESTAMP=… FORMAT=…]   plus old imports containing HTML.
// parseRich() turns that into blocks the app can draw.

export type Span = { text: string; bold?: boolean; italic?: boolean; link?: string; mention?: boolean };

export type Block =
  | { type: 'text'; spans: Span[] }
  | { type: 'image'; url: string }
  | { type: 'file'; name: string; url: string }
  | { type: 'voice'; url: string; duration: string | null };

const SPLIT = /(\[disk\s+file\s+id=n\d+[^\]]*\]|\[img\].*?\[\/img\]|\[file name="[^"]*"\].*?\[\/file\]|\[voice(?:\s+dur="[^"]*")?\].*?\[\/voice\])/gis;

export function decodeEntities(s: string): string {
  return s
    .replace(/&nbsp;/gi, ' ')
    .replace(/&lt;/gi, '<')
    .replace(/&gt;/gi, '>')
    .replace(/&quot;/gi, '"')
    .replace(/&#0*39;/g, "'")
    .replace(/&amp;/gi, '&');
}

/** Imported descriptions sometimes carry HTML: keep the line breaks, drop the tags. */
function stripHtml(s: string): string {
  if (!/<\/?[a-z][^>]*>/i.test(s)) return s;
  return s
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<\/(p|div|li|h[1-6])>/gi, '\n')
    .replace(/<li[^>]*>/gi, '• ')
    .replace(/<[^>]+>/g, '');
}

function spansOf(raw: string): Span[] {
  const text = decodeEntities(stripHtml(raw));
  const spans: Span[] = [];
  // tokens: [b]…[/b] [i]…[/i] [url=..]..[/url] [url]..[/url] [USER=n]..[/USER] [TIMESTAMP=..], bare links
  const re = /\[b\]([\s\S]*?)\[\/b\]|\[i\]([\s\S]*?)\[\/i\]|\[url=([^\]]+)\]([^[]*)\[\/url\]|\[url\](.*?)\[\/url\]|\[USER=\d+\]([^[]*)\[\/USER\]|\[TIMESTAMP=(\d+)\s+FORMAT=[^\]]*\]|(https?:\/\/[^\s<>"'[\]]+)|\[\/?\w[^\]]*\]/gi;
  let last = 0;
  let m: RegExpExecArray | null;
  const push = (s: Span) => {
    if (s.text) spans.push(s);
  };
  while ((m = re.exec(text))) {
    push({ text: text.slice(last, m.index) });
    if (m[1] !== undefined) push({ text: m[1], bold: true });
    else if (m[2] !== undefined) push({ text: m[2], italic: true });
    else if (m[3] !== undefined) push({ text: m[4] || m[3], link: m[3] });
    else if (m[5] !== undefined) push({ text: m[5], link: m[5] });
    else if (m[6] !== undefined) push({ text: m[6], mention: true });
    else if (m[7] !== undefined) push({ text: new Date(parseInt(m[7], 10) * 1000).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }), bold: true });
    else if (m[8] !== undefined) push({ text: m[8], link: m[8] });
    // any other [tag] is dropped
    last = m.index + m[0].length;
  }
  push({ text: text.slice(last) });
  return spans;
}

export function parseRich(raw: string | null | undefined, resolveDiskFile?: (id: string) => string): Block[] {
  const blocks: Block[] = [];
  for (const part of (raw ?? '').split(SPLIT)) {
    if (!part) continue;
    const disk = part.match(/^\[disk\s+file\s+id=n(\d+)[^\]]*\]$/i);
    const img = part.match(/^\[img\](.*?)\[\/img\]$/is);
    const file = part.match(/^\[file name="([^"]*)"\](.*?)\[\/file\]$/is);
    const voice = part.match(/^\[voice(?:\s+dur="([^"]*)")?\](.*?)\[\/voice\]$/is);
    if (disk) {
      if (resolveDiskFile) blocks.push({ type: 'image', url: resolveDiskFile(disk[1]) });
    } else if (img) blocks.push({ type: 'image', url: img[1].trim() });
    else if (file) blocks.push({ type: 'file', name: decodeEntities(file[1]), url: file[2].trim() });
    else if (voice) blocks.push({ type: 'voice', url: voice[2].trim(), duration: voice[1] ?? null });
    else {
      const spans = spansOf(part);
      if (spans.some((s) => s.text.trim() !== '')) {
        // trim the line breaks that surround an attachment tag
        spans[0] = { ...spans[0], text: spans[0].text.replace(/^\s+/, '') };
        const lastIx = spans.length - 1;
        spans[lastIx] = { ...spans[lastIx], text: spans[lastIx].text.replace(/\s+$/, '') };
        blocks.push({ type: 'text', spans });
      }
    }
  }
  return blocks;
}

/** One line for previews and for a plain-text edit box: tags become a word, tags stripped. */
export function plainText(raw: string | null | undefined): string {
  return parseRich(raw)
    .map((b) => (b.type === 'text' ? b.spans.map((s) => s.text).join('') : b.type === 'image' ? '[image]' : b.type === 'file' ? '[file]' : '[voice]'))
    .join('\n')
    .trim();
}

/** True when the text carries attachments the plain edit box could not keep intact. */
export function hasAttachmentTags(raw: string | null | undefined): boolean {
  return /\[(img|file\b|voice|disk\s+file)/i.test(raw ?? '');
}
