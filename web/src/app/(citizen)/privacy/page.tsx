import type { Metadata } from 'next';
import Link from 'next/link';
import { PortalHeader } from '@/components/citizen/portal-header';
import { SiteFooter } from '@/components/citizen/site-footer';
import { Speedy } from '@/components/citizen/speedy';
import { PageIntro } from '@/components/citizen/page-intro';

export const metadata: Metadata = {
  title: 'Privacy Notice — SPeED TraQR',
  description:
    'How SPeED TraQR handles the personal information you give when you file or track a request, under the Data Privacy Act of 2012 (RA 10173).',
};

/**
 * Privacy notice (RA 10173, Data Privacy Act of 2012).
 *
 * The request form's consent checkbox and the footer both link here, so a
 * citizen can read what they are agreeing to before they agree.
 *
 * Every statement below describes what the system actually does. The two facts
 * the code cannot know — how long records are kept, and who the City's Data
 * Protection Officer is — are marked "To be confirmed" until the City supplies
 * them. DO NOT PUBLISH with those markers still showing.
 */
const UPDATED = 'October 17, 2026';

const SECTIONS: Array<[string, string]> = [
  ['collect', 'What we collect'],
  ['use', 'How we use it'],
  ['see', 'Who can see it'],
  ['public', 'What the public tracking page shows'],
  ['keep', 'How long we keep it'],
  ['protect', 'How we protect it'],
  ['rights', 'Your rights'],
  ['contact', 'Contact and complaints'],
];

/** A fact only the City can supply — deliberately conspicuous until it does. */
function Tbc({ children }: { children: React.ReactNode }) {
  return (
    <span className="rounded bg-amber-100 px-1.5 py-0.5 font-semibold text-amber-900">
      [{children}]
    </span>
  );
}

function Section({
  id,
  title,
  children,
}: {
  id: string;
  title: string;
  children: React.ReactNode;
}) {
  return (
    <section id={id} className="mt-10 scroll-mt-24">
      <h2 className="text-[22px] font-extrabold text-[#01400a] sm:text-[25px]">{title}</h2>
      <div className="mt-3 space-y-3 text-[15.5px] leading-relaxed text-[#2c3a31] sm:text-[16.5px]">
        {children}
      </div>
    </section>
  );
}

const LIST = 'mt-2 list-disc space-y-1.5 pl-6';

export default function PrivacyPage() {
  return (
    <>
      <PortalHeader />

      <main id="main" className="mx-auto w-full max-w-[1209.5px] px-4 pb-16 pt-10 sm:px-6 sm:pt-14 lg:px-8">
        <div className="max-w-[820px]">
          <PageIntro eyebrow="Data privacy" title="Privacy Notice">
            How SPeED TraQR handles the personal information you give when you file or track a
            request, under the Data Privacy Act of 2012 (RA&nbsp;10173).
          </PageIntro>

          <p className="mt-4 text-[14px] font-semibold text-[#4b5a50]">Last updated: {UPDATED}</p>

          <nav aria-label="On this page" className="cz-card mt-8 px-5 py-4">
            <p className="text-[13px] font-bold uppercase tracking-wide text-[#4b5a50]">
              On this page
            </p>
            <ul className="mt-2 flex flex-wrap gap-x-5 gap-y-1.5 text-[15px]">
              {SECTIONS.map(([anchor, label]) => (
                <li key={anchor}>
                  <a
                    href={`#${anchor}`}
                    className="font-semibold text-[#01721a] underline-offset-2 hover:underline"
                  >
                    {label}
                  </a>
                </li>
              ))}
            </ul>
          </nav>

          <Section id="collect" title="What we collect">
            <p>
              When you submit a request, we collect only what is needed to process it and reach you
              about it:
            </p>
            <ul className={LIST}>
              <li>your full name and email address;</li>
              <li>your contact number, if you choose to give one;</li>
              <li>the type of request, its purpose and any description you write;</li>
              <li>the requirement files you upload (images, PDFs or Word documents);</li>
              <li>messages and files you send to the office later, from your tracking page.</li>
            </ul>
            <p>
              The system also records each step of your request: when it was filed, every office
              that scanned it in or out, and who released it.
            </p>
          </Section>

          <Section id="use" title="How we use it">
            <p>We use your information only to handle the request you filed:</p>
            <ul className={LIST}>
              <li>to route it to the offices that must act on it;</li>
              <li>to email you your tracking number, QR code and status updates;</li>
              <li>to contact you if a requirement needs to be corrected or something is missing;</li>
              <li>to release the finished document to you.</li>
            </ul>
            <p>
              Processing records are also used, without names, to measure processing times against
              the targets set by the Ease of Doing Business Act (RA&nbsp;11032). We do not sell your
              information or use it for advertising.
            </p>
          </Section>

          <Section id="see" title="Who can see it">
            <p>
              Only authorised City Hall staff can see your personal details, and only staff of the
              offices your request is routed to. Every action they take on it is logged with their
              name, so access can be audited.
            </p>
            <p>
              Uploaded files are stored privately. They are never posted on a public web address and
              can be opened only by signed-in staff of the offices concerned.
            </p>
          </Section>

          <Section id="public" title="What the public tracking page shows">
            <p>
              Anyone with your tracking number or QR code can see your request&rsquo;s current
              status, the office that has it, and its routing history, and can print its claim slip,
              which carries your name so staff can release the document to you.
            </p>
            <p>
              Your email address and contact number are shown only partly hidden (for example,
              j&bull;&bull;&bull;@gmail.com and &bull;&bull;&bull;4567), enough for you to recognise
              them. Your uploaded files are never shown there. Keep your tracking number and QR code
              to yourself, as you would a claim stub.
            </p>
          </Section>

          <Section id="keep" title="How long we keep it">
            <p>
              We keep your request and its files for{' '}
              <Tbc>To be confirmed: retention period set by the City</Tbc> after it is released, as
              required for government records. After that period, personal details and files are
              securely deleted.
            </p>
          </Section>

          <Section id="protect" title="How we protect it">
            <ul className={LIST}>
              <li>Staff accounts are protected by passwords and given only the access their role needs.</li>
              <li>Uploaded files are kept on private storage, not on a public web address.</li>
              <li>
                QR codes are signed, so ordinary scanner apps cannot read them and they cannot be
                forged.
              </li>
              <li>
                The site uses only the cookies it needs to keep your session secure. There are no
                advertising or tracking cookies.
              </li>
            </ul>
          </Section>

          <Section id="rights" title="Your rights">
            <p>Under the Data Privacy Act you have the right to:</p>
            <ul className={LIST}>
              <li>be informed of how your personal information is processed;</li>
              <li>access the personal information we hold about you;</li>
              <li>correct information that is wrong or incomplete;</li>
              <li>
                object to processing, or ask for your information to be erased or blocked, where the
                law allows;
              </li>
              <li>be compensated for damages caused by unlawful processing;</li>
              <li>file a complaint with the National Privacy Commission.</li>
            </ul>
            <p>
              Note that withdrawing your information while a request is still in process may mean
              the request cannot be completed.
            </p>
          </Section>

          <Section id="contact" title="Contact and complaints">
            <p>
              To use any of these rights, or to ask about this notice, contact the City&rsquo;s Data
              Protection Officer:
            </p>
            <ul className={LIST}>
              <li>
                <Tbc>To be confirmed: name or office of the Data Protection Officer</Tbc>
              </li>
              <li>
                <Tbc>To be confirmed: DPO email address and phone number</Tbc>
              </li>
            </ul>
            <p>
              For questions about a request, email{' '}
              <a
                href="mailto:speedtraqr@gmail.com"
                className="font-bold text-[#01721a] underline underline-offset-2"
              >
                speedtraqr@gmail.com
              </a>{' '}
              and include your tracking number.
            </p>
            <p>
              If you are not satisfied with our response, you may file a complaint with the{' '}
              <a
                href="https://privacy.gov.ph"
                target="_blank"
                rel="noopener noreferrer"
                className="font-bold text-[#01721a] underline underline-offset-2"
              >
                National Privacy Commission
              </a>
              .
            </p>
          </Section>

          <p className="mt-10 text-[15px]">
            <Link href="/request" className="font-bold text-[#01721a] underline-offset-2 hover:underline">
              Back to the request form
            </Link>
          </p>
        </div>
      </main>

      <SiteFooter />
      <Speedy />
    </>
  );
}
