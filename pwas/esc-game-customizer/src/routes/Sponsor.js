import React, { PureComponent } from 'react';
import { Box, Text } from 'mineral-ui';

export default class Sponsor extends PureComponent {
  render() {
    return (
      <Box>
        <Box>
          <Text as="h1">Sponsor</Text>
        </Box>
        <Box>
          <div style={{ width: '100%', height: 200, border: '1px solid red' }}></div>
        </Box>        
      </Box>
    );
  }
}
