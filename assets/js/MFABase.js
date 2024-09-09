// @copyright   Copyright (C) 2010-2024 Combodo SARL
// @license     http://opensource.org/licenses/AGPL-3.0


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
