const msalConfig = {
  auth: {
    clientId: "5d4a75cc-0336-4450-80c3-8974357fb0d5",        // from Azure Portal
    authority: "https://login.microsoftonline.com/85f795ce-af26-4489-a7a3-35d9420bd137",  // from Azure Portal
    redirectUri: "http://kyt.yadin.com/"
  }
};

const msalInstance = new msal.PublicClientApplication(msalConfig);

document.getElementById("microsoftBtn").addEventListener("click", () => {
  msalInstance.loginRedirect({
    scopes: ["openid", "profile", "email"]
  });
});