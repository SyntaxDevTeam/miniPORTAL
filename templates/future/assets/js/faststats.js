const script = document.getElementById("faststats-analytics");
const consentStorageKey = "miniportal.cookie-consent.v2";

const isDisabled = () => {
  try {
    if (window.localStorage.getItem("disable-faststats") === "true") {
      return true;
    }

    const consent = JSON.parse(window.localStorage.getItem(consentStorageKey) || "null");
    return consent?.necessary !== true || consent?.analytics !== true;
  } catch (_error) {
    return true;
  }
};

if (script && !isDisabled()) {
  const siteKey = (script.dataset.faststatsSiteKey || "").trim();

  if (siteKey !== "") {
    const enabled = (name) => script.dataset[name] === "1";

    import("https://cdn.jsdelivr.net/npm/@faststats/web/+esm")
      .then(({ WebAnalytics }) => {
        new WebAnalytics({
          siteKey,
          autoTrack: true,
          cookieless: enabled("faststatsCookieless"),
          debug: enabled("faststatsDebug"),
          errorTracking: { enabled: enabled("faststatsErrorTracking") },
          webVitals: { enabled: enabled("faststatsWebVitals") },
          sessionReplays: { enabled: enabled("faststatsSessionReplays") },
          consent: {
            mode: "granted",
            pendingBehavior: enabled("faststatsCookieless") ? "anonymous" : "disabled",
          },
        });
      })
      .catch(() => {});
  }
}
