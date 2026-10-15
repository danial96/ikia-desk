import { hasAttachmentTags, parseRich, plainText } from '@/lib/richText';

describe('parseRich()', () => {
  it('splits text, photos and files in order', () => {
    const blocks = parseRich('see this\n[img]https://d/uploads/a.png[/img]\nand [file name="Plan.pdf"]https://d/uploads/b.pdf[/file]');

    expect(blocks.map((b) => b.type)).toEqual(['text', 'image', 'text', 'file']);
    expect(blocks[1]).toEqual({ type: 'image', url: 'https://d/uploads/a.png' });
    expect(blocks[3]).toEqual({ type: 'file', name: 'Plan.pdf', url: 'https://d/uploads/b.pdf' });
  });

  it('reads voice notes with and without a duration', () => {
    expect(parseRich('[voice dur="0:12"]/uploads/v.webm[/voice]')).toEqual([{ type: 'voice', url: '/uploads/v.webm', duration: '0:12' }]);
    expect(parseRich('[voice]/uploads/v.webm[/voice]')).toEqual([{ type: 'voice', url: '/uploads/v.webm', duration: null }]);
  });

  it('styles bold, italic, links and mentions', () => {
    const [b] = parseRich('[b]Hi[/b] [i]there[/i] [url=https://x.com]site[/url] [USER=5]Ali Raza[/USER] http://y.com/z');
    if (b.type !== 'text') throw new Error('expected text');

    expect(b.spans).toEqual(
      expect.arrayContaining([
        { text: 'Hi', bold: true },
        { text: 'there', italic: true },
        { text: 'site', link: 'https://x.com' },
        { text: 'Ali Raza', mention: true },
        { text: 'http://y.com/z', link: 'http://y.com/z' },
      ]),
    );
  });

  it('drops tags it does not know and decodes entities', () => {
    expect(plainText('[size=5]big[/size] &amp; &lt;ok&gt; a&nbsp;b')).toBe('big & <ok> a b');
  });

  it('strips HTML from imported descriptions but keeps line breaks', () => {
    expect(plainText('<p>First</p><p>Second<br>line</p>')).toBe('First\nSecond\nline');
  });

  it('can turn a Bitrix disk file into an image block', () => {
    expect(parseRich('[disk file id=n42 name=x]', (id) => `/disk/${id}`)).toEqual([{ type: 'image', url: '/disk/42' }]);
    expect(parseRich('[disk file id=n42]')).toEqual([]);
  });

  it('copes with empty and missing text', () => {
    expect(parseRich('')).toEqual([]);
    expect(parseRich(null)).toEqual([]);
    expect(parseRich('   \n  ')).toEqual([]);
  });

  it('does not let a tag-looking user string break anything', () => {
    expect(() => parseRich('[img]unterminated [file name="x"] [[[ ]]] [b]open')).not.toThrow();
  });
});

describe('plainText() / hasAttachmentTags()', () => {
  it('names attachments in a one-line preview', () => {
    expect(plainText('look [img]u[/img]')).toBe('look\n[image]');
    expect(hasAttachmentTags('look [img]u[/img]')).toBe(true);
    expect(hasAttachmentTags('[file name="a"]u[/file]')).toBe(true);
    expect(hasAttachmentTags('[b]just bold[/b]')).toBe(false);
    expect(hasAttachmentTags(null)).toBe(false);
  });
});
