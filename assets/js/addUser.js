/**
 * File : addUser.js
 * 
 * This file contain the validation of add user form
 * 
 * Using validation plugin : jquery.validate.js
 * 
 * @author Kishor Mali
 */

$(document).ready(function(){
	if (!jQuery.validator.methods.internationalPhone) {
		jQuery.validator.addMethod("internationalPhone", function(value, element) {
			var raw = jQuery.trim(value || "");
			var digits = raw.replace(/\D/g, "");
			if (raw.indexOf("00") === 0) { digits = digits.substring(2); }
			return this.optional(element) || (/^(?:\+|00)?[0-9][0-9\s().-]*[0-9]$/.test(raw) && digits.length >= 7 && digits.length <= 15 && ((raw.indexOf("+") !== 0 && raw.indexOf("00") !== 0) || digits.charAt(0) !== "0"));
		}, "Enter a valid phone number with 7 to 15 digits.");
	}
	
	var addUserForm = $("#addUser");
	
	var validator = addUserForm.validate({
		
		rules:{
			fname :{ required : true },
			email : { required : true, email : true, remote : { url : baseURL + "checkEmailExists", type :"post"} },
			password : { required : true },
			cpassword : {required : true, equalTo: "#password"},
			mobile : { required : true, internationalPhone : true },
			role : { required : true, selected : true}
		},
		messages:{
			fname :{ required : "This field is required" },
			email : { required : "This field is required", email : "Please enter valid email address", remote : "Email already taken" },
			password : { required : "This field is required" },
			cpassword : {required : "This field is required", equalTo: "Please enter same password" },
			mobile : { required : "This field is required" },
			role : { required : "This field is required", selected : "Please select atleast one option" }			
		}
	});
});
