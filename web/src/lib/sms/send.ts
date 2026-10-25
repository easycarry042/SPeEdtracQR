import { after } from 'next/server';

export type SmsMessage = {
  to: string;
  text: string;
  trackingNumber?: string;
};

/**
 * Dispatch an outbound SMS message to a citizen contact number.
 * Supports SMS_GATEWAY_URL / SMS_API_KEY environment variables,
 * or safely logs the notification in development/staging environments.
 */
export async function sendSms(message: SmsMessage): Promise<void> {
  const gatewayUrl = process.env.SMS_GATEWAY_URL;
  const apiKey = process.env.SMS_API_KEY;

  if (gatewayUrl) {
    const res = await fetch(gatewayUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...(apiKey ? { Authorization: `Bearer ${apiKey}` } : {}),
      },
      body: JSON.stringify({
        to: message.to,
        message: message.text,
        tracking_number: message.trackingNumber,
      }),
    });

    if (!res.ok) {
      throw new Error(`SMS gateway responded with HTTP ${res.status}`);
    }
  } else {
    // Development / non-configured fallback
    if (process.env.NODE_ENV !== 'test') {
      console.log(`[sms] Outbound SMS dispatched to ${message.to}: "${message.text}"`);
    }
  }
}

/**
 * Queue SMS send without delaying the HTTP response, mirroring queueMail().
 */
export function queueSms(message: SmsMessage): void {
  const send = (): Promise<void> =>
    sendSms(message).catch((error: unknown) => {
      console.error(`[sms] Failed to send SMS to ${message.to}:`, error);
    });

  try {
    after(send);
  } catch {
    void send();
  }
}
