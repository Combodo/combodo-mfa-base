// @copyright   Copyright (C) 2010-2024 Combodo SARL
// @license     http://opensource.org/licenses/AGPL-3.0

function SetAsDefaultMode(sAppRootURL, sMFAUserSettingsClass)
{
	let successMessage = $('#success-message');
	let errorMessage = $("#error-message");

	$.ajax({
		method: "POST",
		url: sAppRootURL+"pages/exec.php",
		data: {
			"exec_module": "combodo-mfa-base",
			"exec_page": "index.php",
			"operation": "SetAsDefaultMode",
			"class": sMFAUserSettingsClass
		},
		success: function(data) {
			if (data.code === 0) {
				if (data.message) {
					$('#success-message-content').html(data.message);
					successMessage.removeClass('ibo-is-hidden')
				}
			} else {
				if (data.error) {
					$('#error-message-content').html(data.error);
					errorMessage.removeClass('ibo-is-hidden')
				}
			}
		}
	});
}

async function WriteClipboardText(text, messageOk) {
	let successMessage = $('#success-message');
	let errorMessage = $("#error-message");

	try {
		await navigator.clipboard.writeText(text);
		$('#success-message-content').html(messageOk);
		successMessage.removeClass('ibo-is-hidden');
	} catch (error) {
		$('#error-message-content').html(error.message);
		errorMessage.removeClass('ibo-is-hidden');
	}
}
