export const blogPosts = [
  {
    id: "post-1",
    slug: "selecting-microcontroller-iot",
    title: "Selecting the Right Microcontroller for Low-Power IOT",
    excerpt: "A comprehensive guide to balancing performance, power consumption, and peripheral support when choosing an MCU for battery-operated devices.",
    content: `
      <h2>Introduction</h2>
      <p>The Internet of Things (IoT) is driving a massive proliferation of connected devices, many of which must operate on batteries for years. At the heart of these devices lies the microcontroller unit (MCU). Selecting the right MCU is critical to the success of any low-power IoT project.</p>
      
      <h2>Key Considerations</h2>
      <h3>1. Power Consumption Profiles</h3>
      <p>It's not just about active power consumption. For most IoT devices, the MCU spends 99% of its time in sleep or deep sleep modes. Therefore, the standby current is often more important than the active current. Look for MCUs that offer multiple power modes, allowing you to turn off peripherals and clocks that aren't actively needed.</p>
      
      <h3>2. Wake-up Capabilities</h3>
      <p>When the MCU is in deep sleep, it needs a way to wake up without drawing significant current. Features like low-power timer wake-ups, external interrupt pins, and even analog watchdogs (waking up when a sensor reading crosses a threshold) are incredibly valuable.</p>
      
      <h3>3. Wireless Connectivity Integration</h3>
      <p>Should you use an MCU with an integrated RF transceiver (like Bluetooth Low Energy, Wi-Fi, or LoRa) or a separate two-chip solution? Integrated Systems-on-Chip (SoCs) often provide the lowest overall system power because sleep states can be tightly coordinated between the radio and the application processor.</p>
      
      <h2>Conclusion</h2>
      <p>Selecting the right MCU for low-power IoT requires a holistic view of the system architecture. By carefully analyzing the power profiles, peripheral requirements, and software ecosystem, engineers can design devices that achieve true longevity in the field.</p>
    `,
    author: "Dr. Alex Chen",
    role: "Embedded Systems Lead",
    date: "April 4, 2026",
    category: "Hardware Design",
    image: "/blog_microcontroller.png",
    readTime: "5 min read"
  },
  {
    id: "post-2",
    slug: "top-sensors-pcb-design",
    title: "Top 10 Sensors for PCB Design in 2026",
    excerpt: "From ultra-precise environmental monitoring to advanced inertial measurement units, these are the top sensors shaping the hardware landscape this year.",
    content: `
      <h2>The Evolution of Sensing</h2>
      <p>Sensors are the eyes and ears of any modern electronic system. In 2026, we are seeing a shift towards highly integrated, low-power smart sensors that process data at the edge before sending it to the main controller.</p>
      
      <h2>Environmental Monitoring at the Edge</h2>
      <p>Multi-pixel gas sensors and particulate matter sensors are becoming smaller and more accurate. These sensors are increasingly integrated into smart home devices and industrial safety equipment. The trend is moving away from raw analog outputs to complete digital interfaces (like I3C) that provide calibrated, compensated data.</p>
      
      <h2>Advanced motion tracking</h2>
      <p>6-axis and 9-axis Inertial Measurement Units (IMUs) have reached incredible levels of precision. We are now seeing IMUs with dedicated machine learning cores inside the sensor package itself. This allows the sensor to recognize specific motion patterns (like a fall, or a specific gesture) without waking up the main microcontroller, saving significant system power.</p>
      
      <h2>Looking Ahead</h2>
      <p>The integration of sensing and processing at the source is reducing the bandwidth required between the sensor and the host, enabling more complex systems on smaller boards.</p>
    `,
    author: "Sarah Jenkins",
    role: "Senior Hardware Engineer",
    date: "March 28, 2026",
    category: "Components",
    image: "/blog_sensors.png",
    readTime: "7 min read"
  },
  {
    id: "post-3",
    slug: "electava-unified-ecosystem",
    title: "Electava Ecosystem: A Unified Approach to Component Management",
    excerpt: "How our new unified architecture brings together the marketplace, API bridge, and internal workspace for seamless operations.",
    content: `
      <h2>Bridging the Gap</h2>
      <p>Managing a massive catalog of electronic components presents unique challenges. Not only do customers need lightning-fast search and detailed parametric filtering, but vendors and internal teams need robust tools to manage inventory, pricing, and compliance data.</p>
      
      <h2>The Three-Tier Architecture</h2>
      <p>The new Electava ecosystem solves this by decoupling the customer experience from the back-office operations, while maintaining a single source of truth.</p>
      
      <ul>
        <li><strong>The Next.js Marketplace:</strong> A high-performance, statically generated frontend that ensures customers find what they need instantly, regardless of device or connection speed.</li>
        <li><strong>The Node.js API Bridge:</strong> A scalable intermediary that securely exposes data from the core database, providing GraphQL and REST endpoints for our frontend and third-party integrations.</li>
        <li><strong>The PHP Workspace:</strong> A specialized internal dashboard tailored for complex vendor management, auditing, and high-volume data ingestion.</li>
      </ul>
      
      <h2>The Result</h2>
      <p>This separation of concerns allows our frontend teams to iterate rapidly on the user experience without worrying about disrupting core backend processes, while providing unparalleled security for our sensitive manufacturing data.</p>
    `,
    author: "Elena Rodriguez",
    role: "VP of Engineering",
    date: "March 15, 2026",
    category: "Architecture",
    image: "/blog_ecosystem.png",
    readTime: "4 min read"
  }
];

export function getPostBySlug(slug) {
  return blogPosts.find(post => post.slug === slug);
}

export function getAllCategories() {
  const categories = new Set(blogPosts.map(post => post.category));
  return Array.from(categories);
}
