const MAPS_API_KEY = import.meta.env.VITE_MAP_API;
// const MAPS_API_KEY = process.env.MAPS_API_KEY;
const SCRIPT_ID = 'google-maps-script';

let loadPromise: Promise<void> | null = null;

export function useGoogleMaps() {
    const load = (): Promise<void> => {
        if (loadPromise) {
            return loadPromise;
        }

        if (document.getElementById(SCRIPT_ID)) {
            loadPromise = Promise.resolve();
            return loadPromise;
        }

        loadPromise = new Promise((resolve, reject) => {
            const callbackName = '__googleMapsCallback';
            (window as Record<string, unknown>)[callbackName] = () => resolve();

            const script = document.createElement('script');
            script.id = SCRIPT_ID;
            script.src = `https://maps.googleapis.com/maps/api/js?key=${MAPS_API_KEY}&libraries=drawing,places&callback=${callbackName}`;
            script.async = true;
            script.defer = true;
            script.onerror = reject;
            document.head.appendChild(script);
        });

        return loadPromise;
    };

    return { load };
}
