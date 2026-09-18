import { useEffect, useRef } from 'react'

/**
 * Minimal Leaflet pin picker. Leaflet and its CSS load on demand. Calls
 * onPick(lat, lng) whenever the marker is dragged or the map is clicked.
 */
export default function MapPicker({ lat, lng, zoom = 13, onPick, height = 240 }) {
  const nodeRef = useRef(null)
  const mapRef = useRef(null)
  const markerRef = useRef(null)
  const onPickRef = useRef(onPick)

  useEffect(() => { onPickRef.current = onPick })

  useEffect(() => {
    let cancelled = false
    ;(async () => {
      const [{ default: L }] = await Promise.all([
        import('leaflet'),
        import('leaflet/dist/leaflet.css'),
      ])
      if (cancelled || !nodeRef.current || mapRef.current) return

      const [icon2x, icon1x, shadow] = await Promise.all([
        import('leaflet/dist/images/marker-icon-2x.png'),
        import('leaflet/dist/images/marker-icon.png'),
        import('leaflet/dist/images/marker-shadow.png'),
      ])
      const icon = L.icon({
        iconRetinaUrl: icon2x.default, iconUrl: icon1x.default, shadowUrl: shadow.default,
        iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41],
      })

      const hasStart = Number.isFinite(lat) && Number.isFinite(lng)
      const start = hasStart ? [lat, lng] : [20, 0]
      const map = L.map(nodeRef.current, { scrollWheelZoom: false }).setView(start, hasStart ? zoom : 2)
      mapRef.current = map
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '&copy; OpenStreetMap contributors',
      }).addTo(map)

      const marker = L.marker(start, { draggable: true, icon }).addTo(map)
      markerRef.current = marker
      const pick = (latlng) => { marker.setLatLng(latlng); onPickRef.current?.(latlng.lat, latlng.lng) }
      marker.on('dragend', () => pick(marker.getLatLng()))
      map.on('click', (event) => pick(event.latlng))
      setTimeout(() => map.invalidateSize(), 0)
    })()

    return () => {
      cancelled = true
      if (mapRef.current) { mapRef.current.remove(); mapRef.current = null; markerRef.current = null }
    }
  }, []) // eslint-disable-line react-hooks/exhaustive-deps

  // Recentre when the caller sets coordinates from elsewhere (e.g. editing a row).
  useEffect(() => {
    if (!mapRef.current || !markerRef.current || !Number.isFinite(lat) || !Number.isFinite(lng)) return
    markerRef.current.setLatLng([lat, lng])
    mapRef.current.setView([lat, lng], Math.max(mapRef.current.getZoom(), zoom))
  }, [lat, lng, zoom])

  return <div ref={nodeRef} className="map-picker" style={{ height }} aria-label="Pick a location on the map" />
}
