<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>ParkEasy - Ограничение Поиска Городом</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />

  <!-- Bootstrap CSS -->
  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
  />

  <style>
    html, body {
      width: 100%; height: 100%;
      margin: 0; padding: 0;
      overflow: hidden;
      font-family: sans-serif;
    }
    #map {
      position: absolute;
      top: 0; left: 0;
      width: 100%; 
      height: 100%;
    }

    /* Нижняя панель (поля ввода) */
    .bottom-panel {
      position: absolute;
      bottom: 0; left: 0;
      width: 100%;
      background-color: rgba(255,255,255,0.9);
      padding: 16px;
      box-shadow: 0 -2px 8px rgba(0,0,0,0.2);
      border-top-left-radius: 16px;
      border-top-right-radius: 16px;
      display: flex;
      flex-direction: column;
      gap: 8px;
      z-index: 1000; /* чтобы находиться выше карты */
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

    /* Панель навигации (шаги) */
    .nav-panel {
      position: absolute;
      bottom: 0; left: 0;
      width: 100%;
      max-height: 40%;
      background-color: rgba(255,255,255,0.95);
      padding: 16px;
      box-shadow: 0 -2px 8px rgba(0,0,0,0.2);
      border-top-left-radius: 16px;
      border-top-right-radius: 16px;
      overflow-y: auto;
      display: none;
      z-index: 2000; 
    }
    .nav-step {
      margin-bottom: 12px;
    }

    /* Кнопка GPS */
    .gps-button {
      position: absolute;
      bottom: 320px; /* Выше панели */
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
      width: 24px; height: 24px;
    }

    /* Модалка: квадратики */
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

    /* Лого (при желании) */
    .logo {
      width: 80px; 
      height: auto;
      margin: 0 auto; 
      display: block;
    }
  </style>
</head>
<body>

<!-- Карта -->
<div id="map"></div>

<!-- Кнопка GPS (записать адрес в "Откуда") -->
<button class="gps-button" id="gpsBtn">
  <img src="../assets/images/location.png" alt="GPS" />
</button>

<!-- Панель ввода (по умолчанию) -->
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
  <button class="main-button" id="routeBtn">Поехали</button>
</div>

<!-- Панель навигации (шаги) -->
<div class="nav-panel" id="navPanel">
  <div id="navSteps"></div>
  <button class="btn btn-danger w-100 mt-2" id="endNavBtn">
    Завершить
  </button>
</div>

<!-- Модальное окно (детали парковки) -->
<div class="modal fade" id="spotsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="spotsModalTitle">Детали парковки</h5>
        <button type="button" class="btn btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <div class="modal-body" id="spotsModalBody">
        <!-- Квадратики -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-success" id="goHereBtn">
          Доехать сюда
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          Закрыть
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap JS -->
<script 
  src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
</script>

<!-- Google Maps (с вашим ключом) -->
<script
  src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDjB46uCIzzBxbQEhfdsggoXmDybRdlnNA&libraries=places,geometry&callback=initMap"
  async
  defer
></script>

<script>
// ======== Глобальные переменные ========
let map;
let directionsService, directionsRenderer;
let fromAutocomplete, toAutocomplete;
let markers = [];       // для кружков парковок
let watchId = null;     // ID watchPosition
let userMarker = null;  // маркер "машины" (пользователя)
let currentSteps = [];  
let currentStepIndex = 0;

// DOM-ссылки
const viewModePanel = document.getElementById('viewModePanel');
const navPanel      = document.getElementById('navPanel');
const navStepsDiv   = document.getElementById('navSteps');

// Координаты парковки (модалка)
let currentParkingLat = null, currentParkingLng = null;

/** Инициализация карты */
function initMap() {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      async (pos) => {
        const lat = pos.coords.latitude;
        const lng = pos.coords.longitude;
        await createMap(lat, lng);
        // После создания карты и автокомплита - ограничим поиск городом
        await limitAutocompleteToCurrentCity(lat, lng);
      },
      async (err) => {
        console.warn('Geo denied, fallback: Moscow');
        await createMap(55.7522, 37.6156);
        // При желании можно ограничить Москвой:
        // await limitAutocompleteToCityByName("Москва");
      }
    );
  } else {
    createMap(55.7522, 37.6156);
    // limitAutocompleteToCityByName("Москва");
  }
}

/** Создание карты и основных объектов */
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

  // Автокомплит
  fromAutocomplete = new google.maps.places.Autocomplete(
    document.getElementById('fromInput')
  );
  toAutocomplete   = new google.maps.places.Autocomplete(
    document.getElementById('toInput')
  );

  // События
  document.getElementById('routeBtn').addEventListener('click', buildRoute);
  document.getElementById('endNavBtn').addEventListener('click', endNavigation);
  document.getElementById('goHereBtn').addEventListener('click', buildRouteToParking);
  document.getElementById('gpsBtn').addEventListener('click', setFromCurrentLocation);

  // Маркер пользователя
  placeUserMarker(lat, lng);

  // Загружаем парковки
  loadParkings();
  setInterval(loadParkings, 5000);
}

/** Ограничить автокомплит городом пользователя */
async function limitAutocompleteToCurrentCity(lat, lng) {
  try {
    // 1) Reverse Geocode, ищем locality
    const revUrl = `https://maps.googleapis.com/maps/api/geocode/json?latlng=${lat},${lng}&key=AIzaSyDjB46uCIzzBxbQEhfdsggoXmDybRdlnNA`;
    const revResp = await fetch(revUrl);
    const revData = await revResp.json();
    if (revData.status!=='OK' || !revData.results.length) {
      console.warn('Не удалось определить город');
      return;
    }

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
    if (!cityName) {
      console.warn('Город не найден');
      return;
    }

    // 2) Forward Geocode
    const fwdUrl = `https://maps.googleapis.com/maps/api/geocode/json?address=${encodeURIComponent(cityName)}&key=AIzaSyDjB46uCIzzBxbQEhfdsggoXmDybRdlnNA`;
    const fwdResp = await fetch(fwdUrl);
    const fwdData = await fwdResp.json();
    if (fwdData.status!=='OK' || !fwdData.results.length) {
      console.warn('Не удалось получить границы города');
      return;
    }

    const cityResult = fwdData.results[0];
    if (!cityResult.geometry.bounds) {
      console.warn('У города нет geometry.bounds');
      return;
    }

    const b = cityResult.geometry.bounds; 
    const cityBounds = new google.maps.LatLngBounds(
      new google.maps.LatLng(b.southwest.lat, b.southwest.lng),
      new google.maps.LatLng(b.northeast.lat, b.northeast.lng)
    );

    fromAutocomplete.setBounds(cityBounds);
    fromAutocomplete.setOptions({ strictBounds: true });
    toAutocomplete.setBounds(cityBounds);
    toAutocomplete.setOptions({ strictBounds: true });

    console.log('Autocomplete ограничен границами города:', cityName);
  } catch(e) {
    console.error(e);
  }
}

/** Загрузка парковок (каждые 5 сек) */
async function loadParkings() {
  try {
    const resp = await fetch('get_data.php');
    if (!resp.ok) throw new Error('Ошибка сети при загрузке парковок');
    const data = await resp.json();
    // data = [{ coords, spots, total, free }, ...]

    markers.forEach(m => m.setMap(null));
    markers = [];

    data.forEach(item => {
      const { coords, spots, total, free } = item;
      if (!coords) return;
      const parts = coords.split(',');
      if (parts.length<2) return;
      const lat = parseFloat(parts[0]);
      const lng = parseFloat(parts[1]);
      if (isNaN(lat)||isNaN(lng)) return;

      const icon = {
        path: google.maps.SymbolPath.CIRCLE,
        fillColor: free>0?'#28a745':'#dc3545',
        fillOpacity:1,
        strokeColor:'#fff',
        strokeWeight:2,
        scale:20
      };
      const label = {
        text: String(free),
        color: '#fff',
        fontSize: '14px',
        fontWeight: 'bold'
      };

      let marker = new google.maps.Marker({
        position: { lat, lng },
        map,
        icon,
        label
      });

      marker.addListener('click', ()=>{
        openParkingModal(spots, free, total, lat, lng);
      });
      markers.push(marker);
    });
  } catch(e) {
    console.error(e);
  }
}

/** Модалка с деталями */
function openParkingModal(spots, free, total, lat, lng) {
  currentParkingLat = lat;
  currentParkingLng = lng;
  document.getElementById('spotsModalTitle').textContent =
    `Свободно: ${free} из ${total}`;

  let squares = '';
  spots.forEach(s => {
    const cssClass = s.is_busy ? 'busy' : 'free';
    squares += `<div class="spot-square ${cssClass}">${s.spot_id}</div>`;
  });
  document.getElementById('spotsModalBody').innerHTML = `
    <div style="display:flex; flex-wrap:wrap;max-width:300px;">
      ${squares}
    </div>
  `;
  
  const modalEl = document.getElementById('spotsModal');
  const modal = new bootstrap.Modal(modalEl, {});
  modal.show();
}

/** «Доехать сюда» */
function buildRouteToParking() {
  if (!currentParkingLat || !currentParkingLng) {
    alert('Нет координат парковки');
    return;
  }
  // Закрываем модалку
  const modalEl = document.getElementById('spotsModal');
  const modal = bootstrap.Modal.getInstance(modalEl);
  modal.hide();

  // origin = userMarker
  const origin = userMarker
    ? userMarker.getPosition()
    : map.getCenter();
  const destination = new google.maps.LatLng(currentParkingLat, currentParkingLng);

  buildRouteFromTo(origin, destination);
}

/** Кнопка «Поехали» */
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
    origin = userMarker
      ? userMarker.getPosition()
      : map.getCenter();
  }
  const destination = toPlace.geometry.location;
  
  buildRouteFromTo(origin, destination);
}

/** Строим маршрут + «самодельная навигация» */
function buildRouteFromTo(origin, destination) {
  directionsService.route({
    origin,
    destination,
    travelMode: google.maps.TravelMode.DRIVING
  }, (response, status)=>{
    if (status==='OK') {
      directionsRenderer.setDirections(response);
      startNavigation(response);
    } else {
      alert('Не удалось построить маршрут: '+status);
    }
  });
}

/** Начинаем навигацию */
function startNavigation(response) {
  viewModePanel.style.display = 'none';
  navPanel.style.display      = 'block';
  navStepsDiv.innerHTML='';

  const route = response.routes[0];
  const leg   = route.legs[0];
  if (!leg) {
    navStepsDiv.innerHTML='<p>Нет шагов</p>';
    return;
  }
  currentSteps = leg.steps;
  currentStepIndex = 0;

  let html='';
  currentSteps.forEach((step,i)=>{
    html+=`
      <div class="nav-step">
        <b>Шаг ${i+1}:</b> ${step.html_instructions}
        <small>(${step.distance.text})</small>
      </div>
    `;
  });
  navStepsDiv.innerHTML=html;

  if (watchId) {
    navigator.geolocation.clearWatch(watchId);
    watchId=null;
  }
  if (navigator.geolocation) {
    watchId = navigator.geolocation.watchPosition(
      pos => {
        const lat=pos.coords.latitude;
        const lng=pos.coords.longitude;
        placeUserMarker(lat, lng);
        checkIfStepDone(lat, lng);
      },
      err => {
        console.warn('watchPosition error', err);
      },
      { enableHighAccuracy:true, timeout:10000 }
    );
  }
}

/** Проверяем, не завершён ли шаг */
function checkIfStepDone(lat, lng) {
  if (currentStepIndex>=currentSteps.length) {
    endNavigation();
    return;
  }
  const step = currentSteps[currentStepIndex];
  const endLoc = step.end_location;
  const dist = google.maps.geometry.spherical.computeDistanceBetween(
    new google.maps.LatLng(lat, lng),
    endLoc
  );
  if (dist<30) {
    currentStepIndex++;
    if (currentStepIndex>=currentSteps.length) {
      endNavigation();
    }
  }
}

/** Завершаем навигацию */
function endNavigation() {
  if (watchId) {
    navigator.geolocation.clearWatch(watchId);
    watchId=null;
  }
  directionsRenderer.set('directions', null);
  currentSteps=[];
  currentStepIndex=0;

  navPanel.style.display='none';
  viewModePanel.style.display='flex';
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
    navigator.geolocation.getCurrentPosition(async (pos)=>{
      const lat=pos.coords.latitude;
      const lng=pos.coords.longitude;

      map.setCenter({ lat, lng });
      map.setZoom(18);

      placeUserMarker(lat,lng);

      // Обратное геокодирование
      const address=await reverseGeocode(lat,lng);
      document.getElementById('fromInput').value = address ||
        `${lat.toFixed(5)},${lng.toFixed(5)}`;
    });
  }
}

/** Обратное геокодирование */
async function reverseGeocode(lat, lng) {
  const url=`https://maps.googleapis.com/maps/api/geocode/json?latlng=${lat},${lng}&key=AIzaSyDjB46uCIzzBxbQEhfdsggoXmDybRdlnNA`;
  try {
    const resp=await fetch(url);
    const data=await resp.json();
    if (data.status==='OK' && data.results.length>0) {
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
