<?php
session_start();

// Если страница только для авторизованных пользователей
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Ключ (можно подгружать из .env)
$gmapsKey = "AIzaSyDjB46uCIzzBxbQEhfdsggoXmDybRdlnNA";
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>EasyPark</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />

  <!-- Bootstrap CSS -->
  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
  />

  <style>
    html, body {
      width: 100%; 
      height: 100%;
      margin: 0; 
      padding: 0;
      overflow: hidden;
      font-family: sans-serif;
    }
    #map {
      position: absolute;
      top: 0; 
      left: 0;
      width: 100%; 
      height: 100%;
    }

    /* Панель ввода (откуда/куда) */
    .bottom-panel {
      position: absolute;
      bottom: 0; 
      left: 0;
      width: 100%;
      background-color: rgba(255,255,255,0.9);
      padding: 16px;
      box-shadow: 0 -2px 8px rgba(0,0,0,0.2);
      border-top-left-radius: 16px;
      border-top-right-radius: 16px;
      display: flex;
      flex-direction: column;
      gap: 8px;
      z-index: 1000; 
    }
    .mobile-input {
      width: 100%;
      padding: 10px;
      border-radius: 6px;
      border: 1px solid #ccc;
      font-size: 16px;
    }
    .main-button {
      padding: 12px;
      border: none;
      border-radius: 8px;
      background-color: #4caf50;
      color: #fff;
      font-size: 16px;
      cursor: pointer;
    }

    /* Панель подтверждения маршрута */
    .route-panel {
      position: absolute;
      bottom: 0;
      left: 0;
      width: 100%;
      background-color: rgba(255,255,255,0.95);
      padding: 16px;
      box-shadow: 0 -2px 8px rgba(0,0,0,0.2);
      border-top-left-radius: 16px;
      border-top-right-radius: 16px;
      display: none;
      flex-direction: column;
      gap: 8px;
      z-index: 1500;
    }

    /* Панель навигации (один шаг) */
    .nav-panel {
      position: absolute;
      bottom: 0; 
      left: 0;
      width: 100%;
      background-color: rgba(255,255,255,0.95);
      padding: 16px;
      box-shadow: 0 -2px 8px rgba(0,0,0,0.2);
      border-top-left-radius: 16px;
      border-top-right-radius: 16px;
      display: none;
      z-index: 2000; 
    }

    /* Кнопка GPS */
    .gps-button {
      position: absolute;
      bottom: 320px; 
      right: 16px;
      z-index: 9999;
      width: 48px; 
      height: 48px;
      border: none;
      border-radius: 50%;
      background-color: rgba(255,255,255,0.9);
      box-shadow: 0 0 5px rgba(0,0,0,0.3);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
    }
    .gps-button img {
      width: 24px; 
      height: 24px;
    }

    /* Квадратики в модалке (парковки) */
    .spot-square {
      width: 30px; 
      height: 30px;
      margin: 2px;
      text-align: center;
      line-height: 30px;
      color: #fff;
      font-weight: bold;
      border: 1px solid #999;
      display: inline-block;
    }
    .busy {
      background-color: red;
    }
    .free {
      background-color: green;
    }

    /* Лого */
    .logo {
      width: 80px; 
      height: auto;
      margin: 0 auto; 
      display: block;
    }
  </style>
</head>
<body>

<div id="map"></div>

<!-- Кнопка GPS -->
<button class="gps-button" id="gpsBtn">
  <img src="../assets/images/location.png" alt="GPS" />
</button>

<!-- Панель ввода (откуда/куда) -->
<div class="bottom-panel" id="viewModePanel">
  <img src="../assets/images/logo.svg" class="logo" alt="ParkEasy Logo" />

  <input 
    type="text"
    id="fromInput"
    class="mobile-input"
    placeholder="Откуда..."
  />
  <input
    type="text"
    id="toInput"
    class="mobile-input"
    placeholder="Куда..."
  />
  <button class="main-button" id="routeBtn">Построить маршрут</button>
</div>

<!-- Панель подтверждения маршрута (Поехали / Отмена) -->
<div class="route-panel" id="confirmRoutePanel">
  <div class="mb-2" id="routeSummary"></div>
  <div class="d-flex justify-content-between">
    <button class="btn btn-secondary w-50 me-2" id="cancelRouteBtn">Отмена</button>
    <button class="btn btn-primary w-50" id="startNavBtn">Поехали</button>
  </div>
</div>

<!-- Панель навигации (один шаг) -->
<div class="nav-panel" id="navPanel">
  <div id="currentStepArea" class="mb-2"></div>
  <button class="btn btn-danger w-100 mt-2" id="endNavBtn">Завершить</button>
</div>

<!-- Модальное окно (детали парковки) -->
<div class="modal fade" id="spotsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="spotsModalTitle">Детали парковки</h5>
        <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <div class="modal-body" id="spotsModalBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-success" id="goHereBtn">Доехать сюда</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap JS -->
<script 
  src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
</script>

<script>
  const gmapsKey = "<?= htmlspecialchars($gmapsKey) ?>";
</script>
<script 
  src="https://maps.googleapis.com/maps/api/js?key=<?= urlencode($gmapsKey) ?>&libraries=places,geometry&callback=initMap"
  async
  defer
></script>

<script>
// ГЛОБАЛЬНЫЕ ПЕРЕМЕННЫЕ
let map;
let directionsService, directionsRenderer;
let fromAutocomplete, toAutocomplete;
let markers = [];  
let watchId = null;     
let userMarker = null;  
let currentSteps = [];  
let currentStepIndex = 0;
let lastRouteResult = null;   // последний построенный маршрут
let finalDestination = null;  // LatLng, куда в итоге нужно доехать (конечная точка)

const viewModePanel = document.getElementById('viewModePanel');
const confirmRoutePanel = document.getElementById('confirmRoutePanel');
const navPanel      = document.getElementById('navPanel');

const routeSummary  = document.getElementById('routeSummary');
const currentStepArea = document.getElementById('currentStepArea');

let currentParkingLat = null;
let currentParkingLng = null;

/** ИниЦИализация карты */
function initMap() {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      async pos => {
        const lat = pos.coords.latitude;
        const lng = pos.coords.longitude;
        await createMap(lat, lng);
        await limitAutocompleteToCurrentCity(lat, lng);
      },
      async err => {
        console.warn('Geo denied, fallback: Moscow');
        await createMap(55.7522, 37.6156);
      }
    );
  } else {
    createMap(55.7522, 37.6156);
  }
}

/** Создание карты */
async function createMap(lat, lng) {
  map = new google.maps.Map(document.getElementById('map'), {
    center: { lat, lng },
    zoom: 12,
    disableDefaultUI: true
  });

  directionsService = new google.maps.DirectionsService();
  directionsRenderer = new google.maps.DirectionsRenderer({
    suppressMarkers: true
  });
  directionsRenderer.setMap(map);

  fromAutocomplete = new google.maps.places.Autocomplete(
    document.getElementById('fromInput')
  );
  toAutocomplete = new google.maps.places.Autocomplete(
    document.getElementById('toInput')
  );

  document.getElementById('routeBtn').addEventListener('click', buildRoute);
  document.getElementById('cancelRouteBtn').addEventListener('click', cancelRoute);
  document.getElementById('startNavBtn').addEventListener('click', startNavigation);
  document.getElementById('endNavBtn').addEventListener('click', endNavigation);
  document.getElementById('gpsBtn').addEventListener('click', setFromCurrentLocation);
  document.getElementById('goHereBtn').addEventListener('click', buildRouteToParking);

  placeUserMarker(lat, lng);

  loadParkings();
  setInterval(loadParkings, 5000);
}

/** Ограничить автокомплит */
async function limitAutocompleteToCurrentCity(lat, lng) {
  try {
    const revUrl = `https://maps.googleapis.com/maps/api/geocode/json?latlng=${lat},${lng}&key=${encodeURIComponent(gmapsKey)}`;
    const revResp = await fetch(revUrl);
    const revData = await revResp.json();
    if (revData.status !== 'OK' || !revData.results.length) return;

    let cityName = null;
    outer:
    for (const result of revData.results) {
      for (const comp of result.address_components) {
        if (comp.types.includes('locality')) {
          cityName = comp.long_name;
          break outer;
        }
      }
    }
    if (!cityName) return;

    const fwdUrl = `https://maps.googleapis.com/maps/api/geocode/json?address=${encodeURIComponent(cityName)}&key=${encodeURIComponent(gmapsKey)}`;
    const fwdResp = await fetch(fwdUrl);
    const fwdData = await fwdResp.json();
    if (fwdData.status !== 'OK' || !fwdData.results.length) return;

    const cityResult = fwdData.results[0];
    if (!cityResult.geometry.bounds) return;

    const b = cityResult.geometry.bounds;
    const cityBounds = new google.maps.LatLngBounds(
      new google.maps.LatLng(b.southwest.lat, b.southwest.lng),
      new google.maps.LatLng(b.northeast.lat, b.northeast.lng)
    );

    fromAutocomplete.setBounds(cityBounds);
    fromAutocomplete.setOptions({ strictBounds: true });
    toAutocomplete.setBounds(cityBounds);
    toAutocomplete.setOptions({ strictBounds: true });

  } catch(e) {
    console.error(e);
  }
}

/** Загрузка парковок */
async function loadParkings() {
  try {
    const resp = await fetch('get_data.php');
    if (!resp.ok) throw new Error('Ошибка сети при загрузке парковок');
    const data = await resp.json();
    // [{ coords, spots, total, free }, ...]

    markers.forEach(m => m.setMap(null));
    markers = [];

    data.forEach(item => {
      const { coords, spots, total, free } = item;
      if (!coords) return;
      const parts = coords.split(',');
      if (parts.length < 2) return;
      const lat = parseFloat(parts[0]);
      const lng = parseFloat(parts[1]);
      if (isNaN(lat) || isNaN(lng)) return;

      const icon = {
        path: google.maps.SymbolPath.CIRCLE,
        fillColor: free > 0 ? '#28a745' : '#dc3545',
        fillOpacity: 1,
        strokeColor: '#fff',
        strokeWeight: 2,
        scale: 20
      };
      const label = {
        text: String(free),
        color: '#fff',
        fontSize: '14px',
        fontWeight: 'bold'
      };

      const marker = new google.maps.Marker({
        position: { lat, lng },
        map,
        icon,
        label
      });

      marker.addListener('click', () => {
        openParkingModal(spots, free, total, lat, lng);
      });
      markers.push(marker);
    });
  } catch(e) {
    console.error(e);
  }
}

/** Модалка */
function openParkingModal(spots, free, total, lat, lng) {
  currentParkingLat = lat;
  currentParkingLng = lng;
  document.getElementById('spotsModalTitle').textContent = `Свободно: ${free} из ${total}`;
  
  let squares = '';
  spots.forEach(s => {
    const cssClass = s.is_busy ? 'busy' : 'free';
    squares += `<div class="spot-square ${cssClass}">${s.spot_id}</div>`;
  });
  document.getElementById('spotsModalBody').innerHTML = `
    <div style="display:flex; flex-wrap:wrap; max-width:300px;">
      ${squares}
    </div>
  `;
  const modalEl = document.getElementById('spotsModal');
  const modal = new bootstrap.Modal(modalEl, {});
  modal.show();
}

/** Доехать сюда */
function buildRouteToParking() {
  if (!currentParkingLat || !currentParkingLng) {
    alert('Нет координат парковки');
    return;
  }
  const modalEl = document.getElementById('spotsModal');
  const modal = bootstrap.Modal.getInstance(modalEl);
  modal.hide();

  const origin = userMarker ? userMarker.getPosition() : map.getCenter();
  const dest = new google.maps.LatLng(currentParkingLat, currentParkingLng);

  buildRouteFromTo(origin, dest);
}

/** Построить маршрут */
function buildRoute() {
  const fromPlace = fromAutocomplete.getPlace();
  const toPlace   = toAutocomplete.getPlace();

  if (!toPlace || !toPlace.geometry) {
    alert('Укажите «Куда»');
    return;
  }

  let origin;
  if (fromPlace && fromPlace.geometry) {
    origin = fromPlace.geometry.location;
  } else {
    origin = userMarker ? userMarker.getPosition() : map.getCenter();
  }
  const destination = toPlace.geometry.location;
  buildRouteFromTo(origin, destination);
}

/** Собственно запрос к Directions API */
function buildRouteFromTo(origin, destination) {
  directionsService.route({
    origin,
    destination,
    travelMode: google.maps.TravelMode.DRIVING
  }, (response, status) => {
    if (status === 'OK') {
      lastRouteResult = response;
      directionsRenderer.setDirections(response);

      // Запоминаем конечную точку
      const route = response.routes[0];
      const leg = route.legs[0];
      if (leg) {
        finalDestination = leg.end_location;
      }
      showConfirmRoute(response);
    } else {
      alert('Не удалось построить маршрут: ' + status);
    }
  });
}

/** Показываем панель подтверждения */
function showConfirmRoute(response) {
  viewModePanel.style.display = 'none';
  navPanel.style.display = 'none';
  confirmRoutePanel.style.display = 'flex';

  const leg = response.routes[0].legs[0];
  if (leg) {
    routeSummary.innerHTML = `
      <p>Маршрут: <b>${leg.distance.text}</b>, ~ <b>${leg.duration.text}</b></p>
    `;
  } else {
    routeSummary.innerHTML = `<p>Маршрут построен</p>`;
  }
}

/** Кнопка "Отмена" */
function cancelRoute() {
  confirmRoutePanel.style.display = 'none';
  directionsRenderer.set('directions', null);
  lastRouteResult = null;
  finalDestination = null;
  viewModePanel.style.display = 'flex';
}

/** "Поехали" */
function startNavigation() {
  if (!lastRouteResult) {
    alert('Нет построенного маршрута');
    return;
  }
  confirmRoutePanel.style.display = 'none';
  doStartNavigation(lastRouteResult);
}

/** Начинаем навигацию (только 1 шаг показываем) */
function doStartNavigation(response) {
  navPanel.style.display = 'block';

  const route = response.routes[0];
  const leg   = route.legs[0];
  if (!leg) {
    currentStepArea.innerHTML = 'Нет шагов';
    return;
  }

  currentSteps     = leg.steps;
  currentStepIndex = 0;
  showCurrentStep(); // показываем первый шаг

  // Запускаем watchPosition
  if (watchId) {
    navigator.geolocation.clearWatch(watchId);
    watchId = null;
  }
  if (navigator.geolocation) {
    watchId = navigator.geolocation.watchPosition(
      pos => {
        const lat = pos.coords.latitude;
        const lng = pos.coords.longitude;
        placeUserMarker(lat, lng);
        map.setCenter({ lat, lng });

        checkIfOffRoute(lat, lng);
        checkIfStepDone(lat, lng);
      },
      err => console.warn(err),
      { enableHighAccuracy: true, timeout: 10000 }
    );
  }
}

/** Отображаем текущий шаг */
function showCurrentStep() {
  if (currentStepIndex >= currentSteps.length) {
    currentStepArea.innerHTML = 'Маршрут пройден';
    return;
  }
  const step = currentSteps[currentStepIndex];
  const instructionText = step.html_instructions || step.instructions || '';
  currentStepArea.innerHTML = `
    <div>
      <h5>Шаг ${currentStepIndex+1} из ${currentSteps.length}</h5>
      <div>${instructionText}</div>
      <small>Расстояние: ${step.distance.text}</small>
    </div>
  `;
}

/** Проверяем, не отклонился ли пользователь от маршрута */
function checkIfOffRoute(lat, lng) {
  if (currentStepIndex >= currentSteps.length) return;

  // Допустим, берём start_location следующего шага
  const step = currentSteps[currentStepIndex];
  const startLoc = step.start_location;
  const dist = google.maps.geometry.spherical.computeDistanceBetween(
    new google.maps.LatLng(lat, lng),
    startLoc
  );
  // Если > 50 метров, считаем, что пользователь ушёл
  if (dist > 50) {
    console.log('Пользователь отклонился, перестраиваем маршрут');
    recalcRoute(lat, lng);
  }
}

/** Перестраиваем маршрут c текущей позиции до finalDestination */
function recalcRoute(lat, lng) {
  if (!finalDestination) {
    // Если не знаем конечную точку, просто завершаем
    endNavigation();
    return;
  }
  // Останавливаем watchPosition на время перестроения, чтобы не вызвать гонку
  if (watchId) {
    navigator.geolocation.clearWatch(watchId);
    watchId = null;
  }

  directionsService.route({
    origin: { lat, lng },
    destination: finalDestination,
    travelMode: google.maps.TravelMode.DRIVING
  }, (response, status) => {
    if (status === 'OK') {
      console.log('Маршрут перестроен');
      lastRouteResult = response;
      directionsRenderer.setDirections(response);

      // Запускаем заново навигацию c нового маршрута
      doStartNavigation(response);
    } else {
      alert('Не удалось перестроить маршрут: ' + status);
      endNavigation();
    }
  });
}

/** Проверяем, не завершён ли шаг */
function checkIfStepDone(lat, lng) {
  if (currentStepIndex >= currentSteps.length) {
    endNavigation();
    return;
  }
  const step = currentSteps[currentStepIndex];
  const endLoc = step.end_location;
  const dist = google.maps.geometry.spherical.computeDistanceBetween(
    new google.maps.LatLng(lat, lng),
    endLoc
  );
  if (dist < 30) {
    currentStepIndex++;
    if (currentStepIndex >= currentSteps.length) {
      // Все шаги пройдены, завершаем
      endNavigation();
    } else {
      // Переходим к следующему шагу
      showCurrentStep();
    }
  }
}

/** Завершаем навигацию */
function endNavigation() {
  if (watchId) {
    navigator.geolocation.clearWatch(watchId);
    watchId = null;
  }
  directionsRenderer.set('directions', null);
  currentSteps = [];
  currentStepIndex = 0;
  lastRouteResult  = null;
  finalDestination = null;

  navPanel.style.display = 'none';
  viewModePanel.style.display = 'flex';
}

/** Маркер пользователя */
function placeUserMarker(lat, lng) {
  if (!userMarker) {
    userMarker = new google.maps.Marker({
      map,
      position: { lat, lng },
      icon: {
        path: google.maps.SymbolPath.FORWARD_CLOSED_ARROW,
        scale: 5,
        fillColor: '#007bff',
        fillOpacity: 1,
        strokeWeight: 1,
        strokeColor: '#fff'
      }
    });
  } else {
    userMarker.setPosition({ lat, lng });
  }
}

/** Кнопка GPS */
async function setFromCurrentLocation() {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(async (pos) => {
      const lat = pos.coords.latitude;
      const lng = pos.coords.longitude;

      map.setCenter({ lat, lng });
      map.setZoom(18);
      placeUserMarker(lat, lng);

      const address = await reverseGeocode(lat, lng);
      document.getElementById('fromInput').value = address 
        || `${lat.toFixed(5)},${lng.toFixed(5)}`;
    });
  }
}

/** Обратное геокодирование */
async function reverseGeocode(lat, lng) {
  const url = `https://maps.googleapis.com/maps/api/geocode/json?latlng=${lat},${lng}&key=${encodeURIComponent(gmapsKey)}`;
  try {
    const resp = await fetch(url);
    const data = await resp.json();
    if (data.status === 'OK' && data.results.length > 0) {
      return data.results[0].formatted_address;
    }
  } catch(e) {
    console.error(e);
  }
  return null;
}
</script>
</body>
</html>
