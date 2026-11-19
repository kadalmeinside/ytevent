import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const publicRoutes = [
    '/',
    '/login',
    '/admin/login',
];

for (const route of publicRoutes) {
    test(`public route ${route} renders`, async ({ page }) => {
        const response = await page.goto(route);

        expect(response).not.toBeNull();
        expect(response.status()).toBeLessThan(400);
        await expect(page.locator('body')).toBeVisible();
    });

    test(`public route ${route} has no critical accessibility violations`, async ({ page }) => {
        await page.goto(route);

        const results = await new AxeBuilder({ page })
            .disableRules(['color-contrast'])
            .analyze();

        const severe = results.violations.filter((violation) =>
            ['critical', 'serious'].includes(violation.impact),
        );

        expect(severe, JSON.stringify(severe, null, 2)).toEqual([]);
    });
}
