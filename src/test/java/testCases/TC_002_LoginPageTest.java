package testCases;

import org.testng.Assert;
import org.testng.annotations.Test;

import pageObjects.HomePage;
import pageObjects.LoginPage;
import pageObjects.MyAccount;
import testBase.BaseClass;

public class TC_002_LoginPageTest extends BaseClass {

	@Test(groups = {"regression","sanity", "master"})
	public void testLogin() {
		
		logger.info("**** Starting TC_002_LoginPageTest ****");
		
		// Home page
		HomePage homePage = new HomePage(driver);
		homePage.clickMyAccount();
		homePage.clickLogin();
		
		// Login page
		LoginPage loginPage = new LoginPage(driver);
		logger.info("setting email address and password");
		loginPage.setTxtMailAddress(propertyFile.getProperty("email"));
		loginPage.setTxtPassword(propertyFile.getProperty("password"));
		loginPage.clickBtnLogin();
		logger.info("clicked on login button on login page");
		
		// My Account
		MyAccount myAccount = new MyAccount(driver);
		boolean editAccountDisplayed = myAccount.isEditAccountDisplayed();
		if(editAccountDisplayed) {
			logger.info("Test case passed");
			Assert.assertTrue(true);
			myAccount.clickLogout();
		}
		else {
			logger.error("Test case failed");
			Assert.fail();
		}
		
        logger.info("**** Finished TC_002_LoginPageTest ****");
		
	}
}
