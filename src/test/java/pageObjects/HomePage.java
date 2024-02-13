package pageObjects;

import org.openqa.selenium.WebDriver;
import org.openqa.selenium.WebElement;
import org.openqa.selenium.support.FindBy;

public class HomePage extends BasePage {

	WebDriver driver;

	// Constructor
	public HomePage(WebDriver driver) {
		super(driver);
	}

	// Elements
	@FindBy(xpath = "//span[normalize-space()='My Account']")
	private WebElement lnkMyAccount;

	@FindBy(xpath = "//a[normalize-space()='Register']")
	private WebElement linkRegister;

	@FindBy(xpath = "//a[normalize-space()='Login']")
	private WebElement linkLogin;

	// Actions
	public void clickMyAccount() {
		lnkMyAccount.click();
	}

	public void clickRegister() {
		linkRegister.click();
	}
	
	public void clickLogin() {
        linkLogin.click();
    }

}
