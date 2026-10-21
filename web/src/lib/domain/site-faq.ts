import { routes } from '@/lib/routes';

/**
 * The site's frequently asked questions, in one place.
 *
 * The landing page renders them and Speedy answers from them, so the two can
 * never disagree. Each entry carries the answer twice — as HTML for the page
 * (it may link) and as plain text for the chat — plus the words a citizen is
 * likely to type when asking it, which is how Speedy picks an answer without
 * calling the language model.
 *
 * Deliberately static: the landing page must render with no database query (a
 * feature test asserts it), so nothing here may touch the database.
 */
export type FaqEntry = {
  question: string;
  answerHtml: string;
  answerText: string;
  keywords: string[];
  /**
   * The general answer is right, but the citizen most likely wants it for their
   * own request — Speedy offers to look that up.
   */
  aboutOwnRequest: boolean;
};

/** Strips tags and decodes entities, as the PHP version did for the chat copy. */
function toText(html: string): string {
  return html
    .replace(/<[^>]*>/g, '')
    .replace(/&nbsp;/g, ' ')
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#0?39;/g, "'")
    .replace(/&rsquo;/g, '’');
}

function entry(
  question: string,
  answerHtml: string,
  keywords: string[],
  aboutOwnRequest = false,
): FaqEntry {
  return { question, answerHtml, answerText: toText(answerHtml), keywords, aboutOwnRequest };
}

const TRACK_LINK_CLASS =
  'font-bold text-[#01721a] underline underline-offset-2 hover:text-[#004004]';

/** The questions shown on the landing page, in display order. */
export function all(): FaqEntry[] {
  const trackUrl = routes.citizenTrack();

  return [
    entry(
      'How can I track the status of my document or request?',
      `Open the <a href="${trackUrl}" class="${TRACK_LINK_CLASS}">Track page</a> and enter your tracking number, or scan the QR code from your confirmation email or printed slip.`,
      ['track', 'status', 'progress', 'follow', 'check on', 'where is'],
      true,
    ),
    entry(
      'Do I need an account to use SPeED TraQR?',
      'No. Anyone can file and track a request without signing up. Keep your tracking number: it is how you follow your request and claim it.',
      ['account', 'sign up', 'signup', 'register', 'log in', 'login', 'password'],
    ),
    entry(
      'Is my personal information safe when I track a document publicly?',
      'Yes. In compliance with the Data Privacy Act of 2012 (RA&nbsp;10173), the public tracking page shows the document’s current status, location and routing history. Your email and phone number appear only partly hidden, and attached files are never shown.',
      ['privacy', 'private', 'personal', 'safe', 'data', 'information', 'secure', 'ra 10173'],
    ),
    entry(
      'What should I do if I lose my tracking number or QR code?',
      'Look for the confirmation email we sent when you filed. It has both your tracking number and your QR code. If you can’t find it, visit the City Hall office where you filed your request.',
      ['lost', 'lose', 'forgot', 'missing', "can't find", 'cannot find', 'no tracking'],
    ),
    entry(
      'What types of documents can be tracked through SPeED TraQR?',
      'Official documents, citizen service requests, permits, endorsements and internal office communications routed across departments of the City Government of San Pedro.',
      [
        'types',
        'kinds',
        'which documents',
        'what documents',
        'what services',
        'which services',
        'what can i',
      ],
    ),
    entry(
      'How does the system estimate when my document will be ready?',
      'It looks at how long similar requests took to finish. Until there is enough history, it uses the standard processing times set under the Ease of Doing Business Act (RA&nbsp;11032).',
      ['estimate', 'when will', 'how long', 'ready', 'processing time', 'eta'],
      true,
    ),
  ];
}

/** Questions Speedy answers that the landing page does not list. */
export function assistantOnly(): FaqEntry[] {
  return [
    entry(
      'How do I file a request?',
      'Open Submit a request, choose the request type, fill in your name and email, and attach any requirements you have. Your tracking number and QR code arrive by email right away. No account is needed.',
      ['file', 'submit', 'apply', 'new request', 'request a', 'how do i get'],
    ),
    entry(
      'How do I claim my document?',
      'When your request says “Ready for pickup”, bring your QR code (from your confirmation email or printed slip) to the City Hall counter. Staff scan it to release your document.',
      ['claim', 'pick up', 'pickup', 'collect', 'release', 'get my document'],
      true,
    ),
    entry(
      'How do I contact the office?',
      'Email speedtraqr@gmail.com and include your tracking number. City Hall offices are open Monday to Friday, 8:00 AM to 5:00 PM, except public holidays.',
      ['contact', 'email', 'phone', 'call', 'office hours', 'open', 'talk to', 'speak to', 'human'],
    ),
  ];
}

/** Everything Speedy may answer from. */
export function forAssistant(): FaqEntry[] {
  return [...all(), ...assistantOnly()];
}
