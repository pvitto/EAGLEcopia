from playwright.sync_api import sync_playwright

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()

    try:
        page.goto("http://localhost:8080/index.php")

        # Make the operator panel visible for the test
        page.evaluate("() => { document.getElementById('content-operador').classList.remove('hidden'); }")

        # Fill in the invoice number and consult
        page.fill("#consult-invoice", "TEST-123")
        page.click("button[type='submit']")

        # Wait for the operator panel to be populated and visible
        page.wait_for_selector("#operator-panel:not(.hidden)", state="visible")

        # Fill in some denomination values
        page.fill('#denomination-form [data-value="50000"] .denomination-qty', "2") # 100,000

        # Click the save button
        page.click("#denomination-form button[type='submit']")

        # Wait for the progress bar to reach 100%
        page.wait_for_function("() => document.getElementById('progress-bar').style.width === '100%'")

        # Take a screenshot
        page.screenshot(path="jules-scratch/verification/verification.png")

    except Exception as e:
        print(f"An error occurred: {e}")
        page.screenshot(path="jules-scratch/verification/error.png")

    finally:
        browser.close()

with sync_playwright() as playwright:
    run(playwright)
