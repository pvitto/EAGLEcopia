from playwright.sync_api import sync_playwright

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()

    try:
        # Login
        page.goto("http://localhost:8080/login.php")
        page.fill("input[name='email']", "admin@example.com")
        page.fill("input[name='password']", "admin_password")
        page.click("button[type='submit']")
        page.wait_for_url("http://localhost:8080/index.php")

        # Navigate to the form builder
        page.click("text=Formulario General")

        # Create a new form
        page.click("text=Crear Nuevo Formulario")
        page.fill("input[type='text']", "Mi Formulario de Prueba")
        page.press("input[type='text']", "Enter")

        # Add a text field
        page.click("button[title='Añadir Campo de Texto Corto']")

        # Take a screenshot
        page.screenshot(path="jules-scratch/verification/verification.png")

    finally:
        browser.close()

with sync_playwright() as playwright:
    run(playwright)
