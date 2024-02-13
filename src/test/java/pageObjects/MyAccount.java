package pageObjects;

import org.openqa.selenium.WebDriver;
import org.openqa.selenium.WebElement;
import org.openqa.selenium.support.FindBy;

public class MyAccount extends BasePage {

	public MyAccount(WebDriver driver) {
		super(driver);
	}

	@FindBy(xpath = "//a[normalize-space()='Edit Account']")
	private WebElement btnEditAccount;

	@FindBy(xpath = "//a[@class='list-group-item'][normalize-space()='Logout']")
	private WebElement btnLogout;
	
	public boolean isEditAccountDisplayed() {
        try {
			return btnEditAccount.isDisplayed();
		} catch (Exception e) {
//			System.out.println("Some exception occured {} " + e);
			return false;
		}
    }
	
	public void clickLogout() {
        btnLogout.click();
    }
	
}
