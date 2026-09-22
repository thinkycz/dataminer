import { expect } from '@playwright/test';
import { existsSync, readFileSync } from 'node:fs';

interface TestEmail {
    to: string[];
    subject: string;
    html: string;
}

export async function latestEmail(recipient: string): Promise<TestEmail> {
    const database = process.env.DATAMINER_E2E_DATABASE;
    if (
        !database ||
        !/^\/private\/tmp\/dataminer-e2e-[0-9a-f-]+\.sqlite$/.test(database)
    )
        throw new Error(
            'The test mailbox requires an isolated browser database.',
        );
    const mailbox = `${database}.mail.jsonl`;
    let message: TestEmail | undefined;
    await expect
        .poll(() => {
            if (!existsSync(mailbox)) return false;
            message = readFileSync(mailbox, 'utf8')
                .trim()
                .split('\n')
                .map((line) => JSON.parse(line) as TestEmail)
                .filter((mail) => mail.to.includes(recipient))
                .at(-1);
            return message !== undefined;
        })
        .toBe(true);
    if (!message)
        throw new Error('The requested test email was not delivered.');
    return message;
}

export function emailActionLink(mail: TestEmail, path: string): string {
    const hrefs = [...mail.html.matchAll(/href="([^"]+)"/g)].map((match) =>
        match[1].replaceAll('&amp;', '&'),
    );
    const link = hrefs.find((href) => href.includes(`${path}?`));
    expect(link, `Email must contain an action link for ${path}`).toBeTruthy();
    if (!link) throw new Error('Missing email action link.');
    const url = new URL(link);
    expect(url.origin).toBe('http://127.0.0.1:8000');
    expect(url.pathname).toBe(path);
    return url.href;
}
