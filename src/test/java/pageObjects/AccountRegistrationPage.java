package pageObjects;

import org.openqa.selenium.WebDriver;
import org.openqa.selenium.WebElement;
import org.openqa.selenium.support.FindBy;

public class AccountRegistrationPage extends BasePage {

	// Constructor
	public AccountRegistrationPage(WebDriver driver) {
		super(driver);
	}

	// WebElements
	@FindBy(xpath = "//input[@id='input-firstname']")
	private WebElement txtFirstName;

	@FindBy(xpath = "//input[@id='input-lastname']")
	private WebElement txtLastName;

	@FindBy(xpath = "//input[@id='input-email']")
	private WebElement txtEmail;

	@FindBy(xpath = "//input[@id='input-telephone']")
	private WebElement txtTelephone;

	@FindBy(xpath = "//input[@id='input-password']")
	private WebElement txtPassword;

	@FindBy(xpath = "//input[@id='input-confirm']")
	private WebElement txtPasswordConfirm;

	@FindBy(xpath = "//input[@name='agree']")
	private WebElement chkdAgree;

	@FindBy(xpath = "//input[@value='Continue']")
	private WebElement btnContinue;

	@FindBy(xpath = "//h1[normalize-space()='Your Account Has Been Created!']")
	private WebElement accountCreateMessage;

	// Actions
	public void setFirstName(String firstName) {
		txtFirstName.sendKeys(firstName);
	}

	public void setLastName(String lastName) {
		txtLastName.sendKeys(lastName);
	}

	public void setEmail(String email) {
		txtEmail.sendKeys(email);
	}

	public void setTelephone(String telephone) {
		txtTelephone.sendKeys(telephone);
	}

	public void setPassword(String password) {
		txtPassword.sendKeys(password);
	}

	public void setPasswordConfirm(String passwordConfirm) {
		txtPasswordConfirm.sendKeys(passwordConfirm);
	}

	public void checkAgree() {
		chkdAgree.click();
	}

	public void clickContinue() {
		// Solution 1
		btnContinue.click();

		// Solution 2
		// btnContinue.submit();

		// Solution 3
		// Actions act=new Actions(driver);
		// act.moveToElement(btnContinue).click().perform();

		// Solution 4
		// JavascriptExecutor js=(JavascriptExecutor)driver;
		// js.executeScript("arguments[0].click();", btnContinue);

		// Solution 5
		// btnContinue.sendKeys(Keys.RETURN);

		// Solution 6
		// WebDriverWait mywait = new WebDriverWait(driver, Duration.ofSeconds(10));
		// mywait.until(ExpectedConditions.elementToBeClickable(btnContinue)).click();

	}

	public boolean isAccountCreated() {
		try {
			return accountCreateMessage.isDisplayed();
		} catch (Exception e) {
			return false;
		}
	}

}
