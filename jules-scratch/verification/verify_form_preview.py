from playwright.sync_api import sync_playwright

def run(playwright):
    browser = playwright.chromium.launch()
    page = browser.new_page()

    # Log in as admin
    page.goto("http://localhost:8080/login.php")
    page.fill('input[name="email"]', 'admin@example.com')
    page.fill('input[name="password"]', 'admin_password')
    page.click('button[type="submit"]')

    # Go to the main page and navigate to the form editor
    page.goto("http://localhost:8080/index.php")
    page.click("text=Formulario General")

    # Take a screenshot of the form list with the "View" button
    page.screenshot(path="jules-scratch/verification/form_list_with_view_button.png")

    # Click the "View" button on the first form
    page.click("text=Ver", first=True)

    # Take a screenshot of the form preview modal
    page.screenshot(path="jules-scratch/verification/form_preview_modal.png")

    browser.close()

with sync_playwright() as playwright:
    run(playwright)
