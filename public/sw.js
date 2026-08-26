const APP_VERSION = new URL(self.location.href).searchParams.get("v") || "development";
const SAFE_VERSION = APP_VERSION.replace(/[^a-zA-Z0-9._-]/g, "-");
const STATIC_CACHE = `growth-alignment-v4-static-${SAFE_VERSION}`;
const NEVER_CACHE_ROUTES = ["/admin", "/report", "/payment"];
const HASHED_ASSET = /(?:^|\/)[^/]*[.-][a-zA-Z0-9_-]{8,}\.(?:css|js|mjs|woff2?|ttf|otf|png|jpe?g|gif|webp|avif|svg)$/i;

const pathMatches = (pathname, prefix) => pathname === prefix || pathname.startsWith(`${prefix}/`);

async function networkFirst(request) {
  return fetch(request, { cache: "no-store" });
}

async function cacheFirst(request) {
  const cached = await caches.match(request);
  if (cached) return cached;

  const response = await fetch(request);
  if (response.headers.get("content-type")?.includes("text/html")) {
    return new Response("Asset not found", { status: 404, headers: { "content-type": "text/plain; charset=utf-8" } });
  }
  if (response.ok && response.type === "basic") {
    const cache = await caches.open(STATIC_CACHE);
    await cache.put(request, response.clone());
  }
  return response;
}

self.addEventListener("install", event => {
  event.waitUntil(caches.open(STATIC_CACHE));
  self.skipWaiting();
});

self.addEventListener("activate", event => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.filter(key => key !== STATIC_CACHE).map(key => caches.delete(key)));
    await self.clients.claim();
  })());
});

self.addEventListener("fetch", event => {
  const { request } = event;
  if (request.method !== "GET") return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  const isApiRequest = pathMatches(url.pathname, "/api");
  const isProtectedRoute = NEVER_CACHE_ROUTES.some(prefix => pathMatches(url.pathname, prefix));
  if (isApiRequest || isProtectedRoute) {
    event.respondWith(networkFirst(request));
    return;
  }

  const isHtmlRequest = request.mode === "navigate" || request.destination === "document" || request.headers.get("accept")?.includes("text/html");
  if (isHtmlRequest || url.pathname === "/" || url.pathname.endsWith("/index.html")) {
    event.respondWith(networkFirst(request));
    return;
  }

  if (HASHED_ASSET.test(url.pathname)) {
    event.respondWith(cacheFirst(request));
  }
});
