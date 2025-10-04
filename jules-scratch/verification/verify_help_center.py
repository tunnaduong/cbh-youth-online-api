from playwright.sync_api import sync_playwright, Page, expect

def run_verification(page: Page):
    # 1. Navigate to the Help Center Index page
    page.goto("http://localhost:5173/help")

    # Wait for the main heading to be visible
    expect(page.get_by_role("heading", name="Chào mừng đến với Trung tâm trợ giúp!")).to_be_visible()

    # 2. Take a screenshot of the index page
    page.screenshot(path="jules-scratch/verification/help-center-index.png")

    # 3. Navigate to a specific article page
    article_link = page.get_by_role("link", name="Cách thay đổi tên người dùng")
    article_link.click()

    # Wait for the article page to load
    expect(page.get_by_role("heading", name="Cách thay đổi tên người dùng")).to_be_visible()

    # Check for breadcrumb
    expect(page.get_by_text("Trung tâm trợ giúp > Cài đặt tài khoản")).to_be_visible()

    # 4. Take a screenshot of the article page
    page.screenshot(path="jules-scratch/verification/help-center-article.png")

    # 5. Navigate to the About page
    about_link = page.get_by_role("link", name="Giới thiệu")
    about_link.click()

    # Wait for the about page to load
    expect(page.get_by_role("heading", name="Về diễn đàn học sinh Chuyên Biên Hòa")).to_be_visible()

    # 6. Take a screenshot of the about page
    page.screenshot(path="jules-scratch/verification/about-page.png")


if __name__ == "__main__":
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        run_verification(page)
        browser.close()