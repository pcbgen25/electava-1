export const categories = [
  {
    id: 'semiconductors',
    name: 'Semiconductors',
    icon: '🔲',
    description: 'ICs, Microcontrollers, Processors & more',
    subcategories: [
      { id: 'microcontrollers', name: 'Microcontrollers (MCUs)' },
      { id: 'processors', name: 'Processors & DSPs' },
      { id: 'memory', name: 'Memory ICs' },
      { id: 'logic-ics', name: 'Logic ICs' },
      { id: 'amplifiers', name: 'Amplifiers & Comparators' },
      { id: 'voltage-regulators', name: 'Voltage Regulators' },
    ],
  },
  {
    id: 'passive-components',
    name: 'Passive Components',
    icon: '⚡',
    description: 'Resistors, Capacitors, Inductors',
    subcategories: [
      { id: 'resistors', name: 'Resistors' },
      { id: 'capacitors', name: 'Capacitors' },
      { id: 'inductors', name: 'Inductors & Coils' },
      { id: 'ferrite-beads', name: 'Ferrite Beads' },
      { id: 'crystals', name: 'Crystals & Oscillators' },
    ],
  },
  {
    id: 'connectors',
    name: 'Connectors',
    icon: '🔌',
    description: 'Headers, Terminals, USB, Power Connectors',
    subcategories: [
      { id: 'board-to-board', name: 'Board-to-Board' },
      { id: 'wire-to-board', name: 'Wire-to-Board' },
      { id: 'usb-connectors', name: 'USB Connectors' },
      { id: 'power-connectors', name: 'Power Connectors' },
      { id: 'rf-connectors', name: 'RF / Coaxial Connectors' },
    ],
  },
  {
    id: 'optoelectronics',
    name: 'Optoelectronics',
    icon: '💡',
    description: 'LEDs, Displays, Optocouplers',
    subcategories: [
      { id: 'leds', name: 'LEDs' },
      { id: 'led-drivers', name: 'LED Drivers' },
      { id: 'displays', name: 'Displays & Modules' },
      { id: 'optocouplers', name: 'Optocouplers' },
      { id: 'photodiodes', name: 'Photodiodes & Sensors' },
    ],
  },
  {
    id: 'power-management',
    name: 'Power Management',
    icon: '🔋',
    description: 'Regulators, Converters, Battery Chargers',
    subcategories: [
      { id: 'dc-dc-converters', name: 'DC-DC Converters' },
      { id: 'ac-dc-converters', name: 'AC-DC Converters' },
      { id: 'battery-management', name: 'Battery Management' },
      { id: 'power-switches', name: 'Power Switches' },
      { id: 'mosfets', name: 'MOSFETs & Transistors' },
    ],
  },
  {
    id: 'sensors',
    name: 'Sensors',
    icon: '📡',
    description: 'Temperature, Motion, Pressure, Gas Sensors',
    subcategories: [
      { id: 'temperature-sensors', name: 'Temperature Sensors' },
      { id: 'motion-sensors', name: 'Motion & IMU Sensors' },
      { id: 'pressure-sensors', name: 'Pressure Sensors' },
      { id: 'current-sensors', name: 'Current Sensors' },
      { id: 'gas-sensors', name: 'Gas & Environmental' },
    ],
  },
  {
    id: 'dev-boards',
    name: 'Development Boards',
    icon: '🛠️',
    description: 'Arduino, Raspberry Pi, ESP32, STM32 Boards',
    subcategories: [
      { id: 'arduino', name: 'Arduino Boards' },
      { id: 'raspberry-pi', name: 'Raspberry Pi' },
      { id: 'esp32', name: 'ESP32 / ESP8266' },
      { id: 'stm32', name: 'STM32 Nucleo & Discovery' },
      { id: 'eval-boards', name: 'Evaluation Boards' },
    ],
  },
  {
    id: 'electromechanical',
    name: 'Electromechanical',
    icon: '⚙️',
    description: 'Relays, Switches, Motors, Fans',
    subcategories: [
      { id: 'relays', name: 'Relays' },
      { id: 'switches', name: 'Switches' },
      { id: 'motors', name: 'Motors & Drivers' },
      { id: 'fans', name: 'Fans & Thermal' },
      { id: 'encoders', name: 'Encoders' },
    ],
  },
];

export function getCategoryById(id) {
  return categories.find(c => c.id === id);
}

export function getSubcategoryById(categoryId, subcategoryId) {
  const category = getCategoryById(categoryId);
  if (!category) return null;
  return category.subcategories.find(s => s.id === subcategoryId);
}
