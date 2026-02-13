import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Configure Axios to send credentials with all requests
axios.defaults.withCredentials = true;

// Optional: Also configure XSRF token for Laravel Sanctum
axios.defaults.withXSRFToken = true; 