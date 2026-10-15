import "../scss/app.scss";

// AdminKit (required)
import "./modules/bootstrap";
import "./modules/sidebar";
import "./modules/theme";
import "./modules/feather";
import "./modules/chartjs";

document.addEventListener('DOMContentLoaded', () => {
	document.querySelectorAll('.content > .container-fluid').forEach((container) => {
		container.classList.add('animate__animated', 'animate__fadeIn', 'animate__faster');
	});
});

// Optional modules
/*
// Forms
import "./modules/flatpickr";

// Maps
import "./modules/vector-maps"; */