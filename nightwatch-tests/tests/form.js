module.exports = {
  'Demo test': function (browser) {
    browser
      .url('/admin/config/people/reset-roles/list')
      .waitForElementVisible('[submit]')
      .setValue('[roles]', 'responsable_biblioteca')
      .pause(1000)      
      .end()
  }
}
