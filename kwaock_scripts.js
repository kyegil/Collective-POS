Number.prototype.moneyFormat = function(format, decimalSeparator, groupingSeparator, currencySymbol) {
	decimalSeparator = typeof decimalSeparator !== 'undefined' ? decimalSeparator : '.';
	groupingSeparator = typeof groupingSeparator !== 'undefined' ? groupingSeparator : '';
	currencySymbol = typeof currencySymbol !== 'undefined' ? currencySymbol : "\u00A4";

	if (typeof format != "string") {
		return '';
	}
	
	var positivePattern = format;
	for (var i=1; i < format.length; i++) {
		if (format.charAt(i) === ";" && format.charAt(i-1) != "\\") {
			var positivePattern = format.substr(0, i);
			var negativePattern = format.substr(i+1);
		}
	}
	
	if(!negativePattern) {
		negativePattern = "-" + positivePattern;
	}
	
	var pattern = this < 0 ? negativePattern : positivePattern;

	var integralPattern = pattern;
	for (var i=1; i < pattern.length; i++) {
		if (pattern.charAt(i) === "." && pattern.charAt(i-1) != "\\") {
			var integralPattern = pattern.substr(0, i);
			var fractionalPattern = pattern.substr(i+1);
		}
	}

	var actualIntegralString = Math.floor(Math.abs(this)).toString();
	var actualIntegralDigits = actualIntegralString.length;
	var integralLength = 0;
	var prefix = '';
	var suffix = '';
	var prefixSet = false;
	var grouping = false;
	var groupSize = 0;
	var digitsBeforeSeparator = 0;
	for (var i=0; i < integralPattern.length; i++) {
		if ((integralPattern.charAt(i) === "#" || integralPattern.charAt(i) === "0") && integralPattern.charAt(i-1) != "\\") {
			integralLength ++;
			groupSize ++;
			if(!grouping) {
				digitsBeforeSeparator ++;
			}
			if(!prefixSet) {
				prefix = integralPattern.slice(0, i);
				prefixSet = true;
			}
			suffix = integralPattern.slice(i + 1);
		}
		if (integralPattern.charAt(i) === "," && integralPattern.charAt(i-1) != "\\") {
			groupSize = 0;
			grouping = true;
		}
	}
	integralPattern = integralPattern.slice(prefix.length);
	prefix = prefix.replace("\u00A4", currencySymbol);
	prefix = prefix.replace("\\0", "0");
	prefix = prefix.replace("\\.", ".");
	prefix = prefix.replace("\\,", ",");
	prefix = prefix.replace("\\#", "#");
	prefix = prefix.replace(/\!\\/, ""); // ?

	var digit = actualIntegralDigits - 1;
	var result = '';
	var patternPosition = integralPattern.length -1;
	while(patternPosition >= 0) {
		if ((integralPattern.charAt(patternPosition) === "#") && integralPattern.charAt(patternPosition-1) != "\\" && digit >= 0) {
			result = actualIntegralString.charAt(digit) + result;
			digit--;
		}
		else if ((integralPattern.charAt(patternPosition) === "#") && integralPattern.charAt(patternPosition-1) != "\\") {
			digit--;
		}
		else if ((integralPattern.charAt(patternPosition) === "0") && integralPattern.charAt(patternPosition-1) != "\\") {
			result = parseInt(actualIntegralString.charAt(digit)) + result;
			digit--;
		}
		else if ((integralPattern.charAt(patternPosition) === ",") && integralPattern.charAt(patternPosition-1) != "\\" && digit >= 0) {
			result = groupingSeparator + result;
		}
		else if ((integralPattern.charAt(patternPosition) === ",") && integralPattern.charAt(patternPosition-1) != "\\") {
			result = result;
		}
		else {
			result = integralPattern.charAt(patternPosition) + result;
		}
		patternPosition--;
	}
	if(actualIntegralDigits > integralLength) {
		if(grouping) {
			var rest = actualIntegralString.slice(0, actualIntegralDigits - integralLength);
			var restPosition = rest.length;
			while(restPosition >= 0) {
				result = rest.slice(-groupSize + digitsBeforeSeparator) + result;
				rest = rest.slice(0, -groupSize + digitsBeforeSeparator);
				digitsBeforeSeparator = 0;
				if(rest.length) {
					result = groupingSeparator + result;
				}
				restPosition -= groupSize;
			}
		}
		else {
			result = actualIntegralString.slice(0, actualIntegralDigits - integralLength) + result;
		}
	}
	
	if(fractionalPattern) {
		var minDecimals = 0;
		var maxDecimals = 0;
		for (var i=0; i < fractionalPattern.length; i++) {
			if ((fractionalPattern.charAt(i) === "#" || fractionalPattern.charAt(i) === "0") && fractionalPattern.charAt(i-1) != "\\") {
				maxDecimals ++;
				suffix = fractionalPattern.slice(i + 1);
			}
			if (fractionalPattern.charAt(i) === "0" && fractionalPattern.charAt(i-1) != "\\") {
				minDecimals ++;
			}
		}
		var fraction = (("0." + ((this.toString().split(".")[1]) || 0)) * 1).toFixed(maxDecimals);

		var fixedDecimals = fraction.substr(2, minDecimals);
		var optionalDecimals = (fraction * 1).toString().slice(2 + minDecimals);
		result += decimalSeparator + fixedDecimals + optionalDecimals;
	}

	suffix = suffix.replace("\u00A4", currencySymbol);
	suffix = suffix.replace("\\0", "0");
	suffix = suffix.replace("\\.", ".");
	suffix = suffix.replace("\\,", ",");
	suffix = suffix.replace("\\#", "#");
	suffix = suffix.replace(/\!\\/, ""); // ?

	result = prefix + result + suffix;

	return result;
}