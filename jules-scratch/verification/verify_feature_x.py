
from playwright.sync_api import sync_playwright

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()

    # Go to the login page and log in
    page.goto("http://localhost:8080/login.php")
    page.get_by_placeholder("Email").fill("admin@example.com")
    page.get_by_placeholder("Contraseña").fill("password")
    page.get_by_role("button", name="Iniciar Sesión").click()

    # Wait for the main page to load
    page.wait_for_url("http://localhost:8080/index.php")

    # Open the reminder form to make it visible
    page.click('button[onclick*="reminder-form"]')

    # Take a screenshot of the page
    page.screenshot(path="jules-scratch/verification/verification.png")

    browser.close()

with sync_playwright() as playwright:
    run(playwright)
