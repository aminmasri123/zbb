/** Read at request time: Inertia may retain an older HTML meta token after login. */
export function applyUploadCsrf(xhr, source = document) {
    const cookie = source.cookie.split(';').map(value => value.trim()).find(value => value.startsWith('XSRF-TOKEN='));
    if (cookie) {
        xhr.setRequestHeader('X-XSRF-TOKEN', decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)));
        return;
    }
    const token = source.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (token) xhr.setRequestHeader('X-CSRF-TOKEN', token);
}
