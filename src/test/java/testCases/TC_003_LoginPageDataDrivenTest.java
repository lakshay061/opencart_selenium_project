package testCases;

import org.testng.Assert;
import org.testng.annotations.Test;

import pageObjects.HomePage;
import pageObjects.LoginPage;
import pageObjects.MyAccount;
import testBase.BaseClass;
import utilities.DataProviders;

public class TC_003_LoginPageDataDrivenTest extends BaseClass {

	@Test(dataProvider = "LoginData", dataProviderClass = DataProviders.class)
	public void testLogin(String email, String password, String status) {

		logger.info("**** Starting TC_003_LoginPageDataDrivenTest ****");

		// Home page
		HomePage homePage = new HomePage(driver);
		homePage.clickMyAccount();
		homePage.clickLogin();

		// Login page
		LoginPage loginPage = new LoginPage(driver);
		logger.info("setting email address and password");
		loginPage.setTxtMailAddress(email);
		loginPage.setTxtPassword(password);
		loginPage.clickBtnLogin();
		logger.info("clicked on login button on login page");

		// My Account
		MyAccount myAccount = new MyAccount(driver);
		boolean editAccountDisplayed = myAccount.isEditAccountDisplayed();
		
		if (status.equalsIgnoreCase("valid")) {
			if (editAccountDisplayed) {
				myAccount.clickLogout();
				logger.info("Test case passed");
				Assert.assertTrue(true);
			} else {
				logger.error("Test case failed");
				Assert.fail();
			}
		} 
		else {
			if (editAccountDisplayed) {
				myAccount.clickLogout();
				logger.error("Test case failed");
				Assert.fail();
			} else {
				logger.info("Test case passed");
				Assert.assertTrue(true);
			}
		}

		logger.info("**** Finished TC_003_LoginPageDataDrivenTest ****");

	}
}
