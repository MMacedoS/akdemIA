import '../css/app.css'

const digitsOnly = (value) => value.replace(/\D+/g, '')

const formatDocument = (value) => {
	const digits = digitsOnly(value).slice(0, 14)

	if (digits.length <= 11) {
		return digits
			.replace(/(\d{3})(\d)/, '$1.$2')
			.replace(/(\d{3})(\d)/, '$1.$2')
			.replace(/(\d{3})(\d{1,2})$/, '$1-$2')
	}

	return digits
		.replace(/(\d{2})(\d)/, '$1.$2')
		.replace(/(\d{3})(\d)/, '$1.$2')
		.replace(/(\d{3})(\d)/, '$1/$2')
		.replace(/(\d{4})(\d{1,2})$/, '$1-$2')
}

const formatPhone = (value) => {
	const digits = digitsOnly(value).slice(0, 11)

	if (digits.length <= 10) {
		return digits
			.replace(/(\d{2})(\d)/, '($1) $2')
			.replace(/(\d{4})(\d{1,4})$/, '$1-$2')
	}

	return digits
		.replace(/(\d{2})(\d)/, '($1) $2')
		.replace(/(\d{5})(\d{1,4})$/, '$1-$2')
}

const normalizeDecimal = (value) => {
	const normalized = value.replace(',', '.').replace(/[^\d.]+/g, '')
	const separatorIndex = normalized.indexOf('.')

	if (separatorIndex === -1) {
		return normalized
	}

	return normalized.slice(0, separatorIndex + 1) + normalized.slice(separatorIndex + 1).replace(/\./g, '')
}

const resolveFieldIdentity = (element) => `${element.name || ''} ${element.id || ''}`.toLowerCase()

const resolveMask = (element) => {
	if (element.dataset.mask) {
		return element.dataset.mask
	}

	const identity = resolveFieldIdentity(element)

	if (/(cpf|cnpj|document)/.test(identity)) {
		return 'document'
	}

	if (/(phone|telefone|celular|whatsapp|contact_phone)/.test(identity)) {
		return 'phone'
	}

	if (element.type === 'number' || /(height|weight|peso|altura|amount|valor|price|preco|port)/.test(identity)) {
		return 'decimal'
	}

	return null
}

const isEmailField = (element) => element.type === 'email' || /(email|mail_from_address)/.test(resolveFieldIdentity(element))

const isSlugField = (element) => /(slug)/.test(resolveFieldIdentity(element))

const toggleValidationState = (element) => {
	if (!('checkValidity' in element)) {
		return
	}

	element.classList.toggle('is-invalid', !element.checkValidity())
}

const enhanceField = (element) => {
	if (!(element instanceof HTMLInputElement || element instanceof HTMLTextAreaElement || element instanceof HTMLSelectElement)) {
		return
	}

	if (element.dataset.formEnhanced === 'true') {
		return
	}

	element.dataset.formEnhanced = 'true'

	if (isEmailField(element)) {
		element.autocapitalize = 'off'
		element.spellcheck = false
		element.autocomplete = element.autocomplete || 'email'

		element.addEventListener('blur', () => {
			element.value = element.value.trim().toLowerCase()
			toggleValidationState(element)
		})
	}

	if (isSlugField(element)) {
		element.autocapitalize = 'off'
		element.spellcheck = false

		element.addEventListener('input', () => {
			element.value = element.value
				.toLowerCase()
				.normalize('NFD')
				.replace(/[\u0300-\u036f]/g, '')
				.replace(/[^a-z0-9-]+/g, '-')
				.replace(/-{2,}/g, '-')
				.replace(/^-|-$/g, '')
		})
	}

	if (element instanceof HTMLInputElement && element.type === 'date') {
		element.lang = 'pt-BR'
	}

	const mask = resolveMask(element)

	if (mask === 'document') {
		element.inputMode = 'numeric'
		element.placeholder = element.placeholder || '000.000.000-00 ou 00.000.000/0000-00'

		element.addEventListener('input', () => {
			element.value = formatDocument(element.value)
		})
	}

	if (mask === 'phone') {
		element.inputMode = 'tel'
		element.placeholder = element.placeholder || '(00) 00000-0000'

		element.addEventListener('input', () => {
			element.value = formatPhone(element.value)
		})
	}

	if (mask === 'decimal') {
		if (element instanceof HTMLInputElement) {
			element.inputMode = element.inputMode || 'decimal'
		}

		element.addEventListener('input', () => {
			if (element instanceof HTMLInputElement && element.type === 'number') {
				element.value = normalizeDecimal(element.value)
				return
			}

			element.value = normalizeDecimal(element.value)
		})
	}

	element.addEventListener('blur', () => {
		toggleValidationState(element)
	})
}

const enhanceForms = (root = document) => {
	root.querySelectorAll('input, textarea, select').forEach(enhanceField)
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', () => enhanceForms())
} else {
	enhanceForms()
}
