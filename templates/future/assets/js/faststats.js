const script = document.getElementById("faststats-analytics");

const isDisabled = () => {
  try {
    return window.localStorage.getItem("disable-faststats") === "true";
  } catch (_error) {
    return false;
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
