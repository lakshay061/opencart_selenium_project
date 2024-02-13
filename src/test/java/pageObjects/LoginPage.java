package pageObjects;

import org.openqa.selenium.WebDriver;
import org.openqa.selenium.WebElement;
import org.openqa.selenium.support.FindBy;

public class LoginPage extends BasePage {

	public LoginPage(WebDriver driver) {
		super(driver);
	}

	@FindBy(xpath = "//input[@id='input-email']")
	private WebElement txtMailAddress;
	
	@FindBy(xpath = "//input[@id='input-password']")
	private WebElement txtPassword;
	
	@FindBy(xpath = "//input[@value='Login']")
	private WebElement btnLogin;

	public void setTxtMailAddress(String mailAddress) {
		txtMailAddress.sendKeys(mailAddress);
	}
	
	public void setTxtPassword(String password) {
        txtPassword.sendKeys(password);
    }
	
	public void clickBtnLogin() {
        btnLogin.click();
    }
}
