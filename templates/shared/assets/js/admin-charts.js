(() => {
  const nodes = [...document.querySelectorAll("[data-amchart]")];
  if (nodes.length === 0) return;

  const loadScript = (src) => new Promise((resolve, reject) => {
    const existing = document.querySelector(`script[src="${src}"]`);
    if (existing?.dataset.loaded === "1") {
      resolve();
      return;
    }
    const script = existing || document.createElement("script");
    script.src = src;
    script.async = false;
    script.addEventListener("load", () => {
      script.dataset.loaded = "1";
      resolve();
    }, { once: true });
    script.addEventListener("error", reject, { once: true });
    if (!existing) document.head.append(script);
  });

  const parseData = (node) => {
    try {
      const data = JSON.parse(node.dataset.chartPayload || "[]");
      return Array.isArray(data) ? data : [];
    } catch {
      return [];
    }
  };

  const reveal = (node) => {
    node.hidden = false;
    const fallback = node.parentElement?.querySelector(".chart-fallback");
    if (fallback) fallback.hidden = true;
  };

  let colors;

  const styleAxis = (renderer) => {
    renderer.labels.template.setAll({ fill: colors.muted, fontSize: 11 });
    renderer.grid.template.setAll({ stroke: colors.grid, strokeOpacity: 0.24 });
  };

  const renderLine = (node, data) => {
    const root = am5.Root.new(node.id);
    root.numberFormatter.set("numberFormat", "#,###");
    const chart = root.container.children.push(am5xy.XYChart.new(root, {
      panX: true,
      wheelX: "panX",
      wheelY: "zoomX",
      paddingLeft: 0,
    }));
    const xRenderer = am5xy.AxisRendererX.new(root, { minGridDistance: 52 });
    const yRenderer = am5xy.AxisRendererY.new(root, {});
    styleAxis(xRenderer);
    styleAxis(yRenderer);
    const xAxis = chart.xAxes.push(am5xy.CategoryAxis.new(root, {
      categoryField: "label",
      renderer: xRenderer,
      tooltip: am5.Tooltip.new(root, {}),
    }));
    const yAxis = chart.yAxes.push(am5xy.ValueAxis.new(root, {
      min: 0,
      extraMax: 0.12,
      renderer: yRenderer,
    }));
    const series = chart.series.push(am5xy.LineSeries.new(root, {
      name: node.dataset.chartLabel || "Wartość",
      xAxis,
      yAxis,
      categoryXField: "label",
      valueYField: "value",
      stroke: colors.primary,
      fill: colors.primary,
      tooltip: am5.Tooltip.new(root, { labelText: "{categoryX}: {valueY}" }),
    }));
    series.strokes.template.setAll({ strokeWidth: 3 });
    series.fills.template.setAll({ visible: true, fillOpacity: 0.12 });
    series.bullets.push(() => am5.Bullet.new(root, {
      sprite: am5.Circle.new(root, {
        radius: 3.5,
        fill: colors.primary,
        stroke: am5.color(0x0b1220),
        strokeWidth: 2,
      }),
    }));
    chart.set("cursor", am5xy.XYCursor.new(root, { behavior: "zoomX", xAxis }));
    xAxis.data.setAll(data);
    series.data.setAll(data);
    series.appear(700);
    chart.appear(700, 80);
  };

  const renderBar = (node, data) => {
    const root = am5.Root.new(node.id);
    root.numberFormatter.set("numberFormat", "#,###");
    const chart = root.container.children.push(am5xy.XYChart.new(root, {
      panX: false,
      panY: false,
      paddingLeft: 0,
    }));
    const yRenderer = am5xy.AxisRendererY.new(root, { inversed: true, minGridDistance: 22 });
    const xRenderer = am5xy.AxisRendererX.new(root, { minGridDistance: 45 });
    styleAxis(yRenderer);
    styleAxis(xRenderer);
    yRenderer.grid.template.set("visible", false);
    const yAxis = chart.yAxes.push(am5xy.CategoryAxis.new(root, {
      categoryField: "label",
      renderer: yRenderer,
    }));
    const xAxis = chart.xAxes.push(am5xy.ValueAxis.new(root, {
      min: 0,
      extraMax: 0.15,
      renderer: xRenderer,
    }));
    const series = chart.series.push(am5xy.ColumnSeries.new(root, {
      xAxis,
      yAxis,
      categoryYField: "label",
      valueXField: "value",
      fill: colors.primary,
      stroke: colors.primary,
      tooltip: am5.Tooltip.new(root, { labelText: "{categoryY}: {valueX}" }),
    }));
    series.columns.template.setAll({ height: am5.percent(62), cornerRadiusTR: 5, cornerRadiusBR: 5 });
    yAxis.data.setAll(data);
    series.data.setAll(data);
    series.appear(650);
    chart.appear(650, 60);
  };

  const renderMap = (node, data) => {
    const root = am5.Root.new(node.id);
    const chart = root.container.children.push(am5map.MapChart.new(root, {
      projection: am5map.geoNaturalEarth1(),
      panX: "translateX",
      panY: "translateY",
      wheelY: "zoom",
      minZoomLevel: 0.9,
    }));
    const countries = new Map();
    data.forEach((point) => {
      const code = String(point.country_code || "").toUpperCase();
      if (/^[A-Z]{2}$/.test(code)) countries.set(code, (countries.get(code) || 0) + Number(point.value || 0));
    });
    const polygons = chart.series.push(am5map.MapPolygonSeries.new(root, {
      geoJSON: am5geodata_worldLow,
      exclude: ["AQ"],
      valueField: "value",
      calculateAggregates: true,
    }));
    polygons.mapPolygons.template.setAll({
      fill: colors.land,
      stroke: am5.color(0x4b5563),
      strokeWidth: 0.7,
      tooltipText: "{name}: {value.formatNumber('#,###')} serwerów",
      interactive: true,
    });
    polygons.mapPolygons.template.states.create("hover", { fill: colors.landHover });
    polygons.set("heatRules", [{
      target: polygons.mapPolygons.template,
      dataField: "value",
      min: am5.color(0x334155),
      max: colors.accent,
      key: "fill",
    }]);
    polygons.data.setAll([...countries].map(([id, value]) => ({ id, value })));

    const points = chart.series.push(am5map.MapPointSeries.new(root, {}));
    points.bullets.push((_root, _series, dataItem) => {
      const value = Math.max(1, Number(dataItem.dataContext?.value || 1));
      return am5.Bullet.new(root, {
        sprite: am5.Circle.new(root, {
          radius: Math.min(18, 5 + Math.sqrt(value) * 2.2),
          fill: colors.primary,
          fillOpacity: 0.82,
          stroke: am5.color(0xd9f3ff),
          strokeWidth: 1.5,
          tooltipText: "{label}: {value.formatNumber('#,###')} serwerów",
        }),
      });
    });
    points.data.setAll(data.map((point) => ({
      ...point,
      geometry: {
        type: "Point",
        coordinates: [Number(point.longitude), Number(point.latitude)],
      },
    })));
    chart.set("zoomControl", am5map.ZoomControl.new(root, {}));
    chart.appear(750, 80);
  };

  const start = async () => {
    try {
      for (const src of [
        "https://cdn.amcharts.com/lib/5/index.js",
        "https://cdn.amcharts.com/lib/5/xy.js",
        "https://cdn.amcharts.com/lib/5/map.js",
        "https://cdn.amcharts.com/lib/5/geodata/worldLow.js",
      ]) await loadScript(src);

      colors = {
        text: am5.color(0xcbd5e1),
        muted: am5.color(0x8290a5),
        grid: am5.color(0x4b5568),
        primary: am5.color(0x54bff5),
        accent: am5.color(0xff7a18),
        land: am5.color(0x252b36),
        landHover: am5.color(0x354052),
      };

      nodes.forEach((node) => {
        const data = parseData(node);
        if (data.length === 0) return;
        if (node.dataset.amchart === "line") renderLine(node, data);
        if (node.dataset.amchart === "bar") renderBar(node, data);
        if (node.dataset.amchart === "map") renderMap(node, data);
        reveal(node);
      });
    } catch (error) {
      console.warn("Nie udało się uruchomić amCharts; pozostawiono statyczny fallback.", error);
    }
  };

  start();
})();
