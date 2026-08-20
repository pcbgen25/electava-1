const express = require('express');
const cors = require('cors');
const dotenv = require('dotenv');
const apiRoutes = require('./routes/api');

dotenv.config();

const app = express();
const PORT = process.env.PORT || 5000;

app.use(cors());
app.use(express.json());

app.use('/api', apiRoutes);

app.get('/', (req, res) => {
    res.json({ message: 'Electava Integration API connected.' });
});

app.listen(PORT, () => {
    console.log(`Server running on port ${PORT}`);
});
